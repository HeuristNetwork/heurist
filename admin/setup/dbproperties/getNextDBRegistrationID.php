<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* getNextRegistrationID.php - request an ID from Heurist master index/db=HeuristMasterIndex, allocates the ID,
* sets metadata in record and details.
* This file is called by registerDB.php
* ONLY ALLOW IN HEURIST Master Index database
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


// TO DO: WE NEED SOME MECHANISM TO AVOID DENIAL OF SERVICE ATTACK WHICH REPEATEDLY REQUESTS REGISTRATIONS

// TODO: We may need to hobble/delete some of the functionality on Heurist_Master_Index to avoid people
// creating unwanted records or importing random crap into it


$dbID = 0;
$error = "";
require_once(dirname(__FILE__)."/../../../common/config/initialise.php");
require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__)."/../../../common/php/utilsMail.php");

$indexdb_user_id = 0; // Flags problem if not reset
$returnData = ''; // String returned to caller, contains dbID or 0, and error message (if any)

// Get parameters passed from registration request
// @ preceding $_REQUEST avoids errors, sets Null if parameter missing
$serverURL = $_REQUEST["serverURL"];
$dbReg = $_REQUEST["dbReg"];
$dbTitle = $_REQUEST["dbTitle"];
$dbVersion = @$_REQUEST["dbVer"];
$usrEmail = $_REQUEST["usrEmail"];
$usrPassword = $_REQUEST["usrPassword"];
$usrName = $_REQUEST["usrName"];
$usrFirstName = $_REQUEST["usrFirstName"];
$usrLastName = $_REQUEST["usrLastName"];

// $var is null, blank, 0 or false --> false
if (!$serverURL || !$dbReg || !$dbTitle || !$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword) { // error in one or more parameters
    $returnData = '0,Bad parameters passed';
    echo $returnData;
    return;
}

//error_log('>>>>>'.$serverURL);

if(strpos($serverURL,'http://')===false){
    $serverURL = 'http://'.$serverURL;
}

if(strpos($serverURL, '//localhost')>0 ||  strpos($serverURL, '//127.0.0.1')>0){
    echo '0,Impossible to register database from local server '.$serverURL;
    return;
}

// was used for random password, no longer needed
function genRandomString() {
    $length = 8;
    $characters = "0123456789abcdefghijklmnopqrstuvwxyz";
    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[mt_rand(0, strlen($characters))];
    }
    return $string;
}

$callingServer = $_SERVER['REMOTE_ADDR'];
// TO DO: we need to check that the script is not being called repeatedly from the same server

define("HEURIST_DB_DESCRIPTOR_RECTYPE", 22); // the record type for database (collection) descriptor records - fixed for Master database

// allocate a new user for this database unless the user's email address is recognised
// If a new user, log the user in and assign the record ownership to that user
// By allocating users on the database based on email address we can allow them to edit their own registrations
// but they can't touch anyone else's

mysql_connection_insert("hdb_Heurist_Master_Index"); // hard-coded master index for the Heurist constellation
// database is located at Heurist.sydney.edu.au (2015 on) and accessed via .../h4 version

// Find the registering user in the index database, make them the owner of the new record
$usrEmail = strtolower(trim($usrEmail));
$res = mysql_query("select ugr_ID, ugr_Name, ugr_Password, ugr_FirstName, ugr_LastName from sysUGrps where lower(ugr_eMail)='".$usrEmail."'");
$indexdb_user_id = null;

// Check if the email address is recognised as a user name
// Added 19 Jan 2012: we also use email for ugr_Name and it must be unique, so check it has not been used
if(($res) && (mysql_num_rows($res) == 0)) { // no user found on email, try querying on user name

    $res = mysql_query("select ugr_ID, ugr_Name, ugr_Password, ugr_FirstName, ugr_LastName from sysUGrps where lower(ugr_Name)='".$usrEmail."'");
}
if($res) { // query OK, now see if we have found the user
    if(mysql_num_rows($res) == 0) { // did not find the user, create a new one and pass back login info
        $res = mysql_query("insert into sysUGrps (`ugr_Name`, `ugr_Password`, `ugr_eMail`, `ugr_Enabled`, `ugr_FirstName`, `ugr_lastName`)
            VALUES  ('$usrEmail','$usrPassword','$usrEmail','y','$usrFirstName','$usrLastName')");
        // Note: we use $usrEmail as user name because the person's name may be repeated across many different users of
        // different databases eg. there are lots of johnsons, which will cause insert statement to fail as ugr_Name is unique.
        if($res) { 	// New user created successfully
            $indexdb_user_id = mysql_insert_id();
            header('Location: ' . HEURIST_BASE_URL . '/common/connect/login.php?db=' . HEURIST_DBNAME . (isset($last_uri) ? '&last_uri=' . urlencode($last_uri) : '')); // TODO: Change to HEURIST_BASE_URL
        } else { // Unable to create the new user
            $error = "Unable to write new user in Heurist master index database\n" .
            "Please contact <a href=mailto:info@heuristNetwork.org>Heurist developers</a> for advice";
            $returnData = $dbID . "," . $error;
            echo $returnData; // if you can't set up user it isn't worth trying to register the database''
        }
    } else { // existing user
        $row = mysql_fetch_row($res);
        $indexdb_user_id = $row[0]; // set the user ID for the user in the index database, everything else is known
    }

    // TODO: It seems like the user ID is not being set properly,
    //       at least that seems to be indicated by the fact that the mailout comes with indexdb_user_id=0

} else {// error trying to find usergroup in UGrps table
    $error = "Unable to execute search for user in Heurist master index database\n" . "Please contact <a href=mailto:info@heuristNetwork.org>Heurist developers</a> for advice";
    $returnData = $dbID . "," . $error;
    echo $returnData; // if you can't set up user it isn't worth trying to register the database
}

// write the core database record describing the database to be registered and allocate registration ID
// This is not a fully valid Heurist record, we let the edit form take care of that
// First look to see if there is an existing registration - note, this uses the URL to find the record, not the registration ID
// TODO: Would be good to have a recaptcha style challenge otherwise can be called repeatedly
// with slight URL variations to spawn multiple registrations of dummy databases

$res = mysql_query("select rec_ID, rec_Title from Records where `rec_URL`='$serverURL'");

if(mysql_num_rows($res) == 0) { // new registration
    $res = mysql_query("insert into Records
        (rec_URL, rec_Added, rec_Title, rec_RecTypeID, rec_AddedByImport, rec_OwnerUGrpID, rec_NonOwnerVisibility,rec_Popularity)
        VALUES  ('".mysql_real_escape_string($serverURL)."', now(), '".mysql_real_escape_string($dbTitle).
        "', " . HEURIST_DB_DESCRIPTOR_RECTYPE . ", 0, $indexdb_user_id, 'public', 99)"); //visibility was 'viewable'
    if (!$res) { // Unable to allocate a new ID
        $error = "Cannot write record in Heurist master index database\nThe URL may have been registered with a previous database.\n" . "Please contact <a href=mailto:info@heuristNetwork.org>Heurist developers</a> for advice";
        $returnData = $dbID . "," . $error;
        echo $returnData;
    } else { // core database record created OK
        $dbID = mysql_insert_id();
        $returnData = $dbID;

        //Write the database title into the details, further data will be entered by the Heurist form
        $res = mysql_query("insert into recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES ('$dbID', ".DT_NAME.", '".mysql_real_escape_string($dbTitle)."')");

        //Write db version as detail
        if($dbVersion){
            $update = "insert into recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES ('$dbID', 335, '$dbVersion')";
            $res = mysql_query($update);
        }

        // Write the record bookmark into the bookmarks table. This allos the user registering the database
        // to see thir lsit of databases as My Bookmarks
        $res = mysql_query("insert into usrBookmarks
            (bkm_UGrpID,bkm_RecID) VALUES ('$indexdb_user_id','$dbID')");


        //send email to administrator about new database registration
        $email_text =
        "There is a new Heurist database registration on the Heurist Master Index\n\n".
        "Database Title:     ".htmlentities($dbTitle)."\n".
        "Registration ID:    ".$dbID."\n". // was $indexdb_user_id, which is always 0 b/cnot yet logged in to master index
        "DB Format Version:  ".$dbVersion."\n\n".
        // "User name:    ".$usrFirstName." ".$usrLastName."\n".  // comes out 'every user' b/c user not set
        // "Email address: ".$usrEmail."\n".                      // comes out 'not set for user 0'
        "Go to the address below to review the database:\n".
        $serverURL;

        $dbowner_Email = get_dbowner_email();
        $email_title = 'Database registration ID: '.$dbID.'. User ['.$indexdb_user_id.']';

        sendEmail($dbowner_Email, $email_title, $email_text, null);
        //END email -----------------------------------


    }
} else {
    // existing registration - used to update title, but this is now handled by metadata edit form
    // This should now not be called any more
    // just incase there was a problem let's return the id.
    $dbID = mysql_fetch_assoc($res);
    if (@$dbID && array_key_exists("rec_ID",$dbID)) {
        $returnData = $dbID["rec_ID"];
    }
}

echo $returnData;
?>
