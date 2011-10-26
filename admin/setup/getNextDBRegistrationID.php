<?php

	/**
	* getNextRegistrationID.php - request an ID from HeuristScholar.org/db=HeuristIndex, allocates the ID,
	* sets metadata in record and details, Ian Johnson 18 Jan 2011.
	* This file is called by registerDB.php
	* ONLY ALLOW IN HEURISTSCHOLAR.ORG index database
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	// TO DO: WE NEED SOME MECHANISM TO AVOID DENIAL OF SERVICE ATTACK WHICH REPEATEDLY REQUESTS REGISTRATIONS

	// We may need to hobble/delete some of the functionality on HeuristIndex to avoid people
	// creating unwanted records or importing random crap into it
	$dbID = 0;
	$error = "";
	require_once(dirname(__FILE__)."/../../common/config/initialise.php");
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    mysql_connection_db_insert("hdb_H3MasterIndex"); // hard-coded master index for the Heurist constellation

    $indexdb_user_id = 0; // Flags problem if not reset
    $returnData = ''; // String returned to caller, contains dbID or 0, and error message (if any)

    // Get parameters passed from registration request
    // @ preceding $_REQUEST avoids errors, sets Null if parameter missing
	$serverURL = $_REQUEST["serverURL"];
	$dbReg = $_REQUEST["dbReg"];
	$dbTitle = $_REQUEST["dbTitle"];
	$usrEmail = $_REQUEST["usrEmail"];
	$usrPassword = $_REQUEST["usrPassword"];
	$usrName = $_REQUEST["usrName"];
	$usrFirstName = $_REQUEST["usrFirstName"];
	$usrLastName = $_REQUEST["usrLastName"];

    // $var is null, blank, 0 or false --> false
	if (!$serverURL || !$dbReg || !$dbTitle || !$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword) { // error in one or more parameters
        $returnData = '0,Bad parameters passed';
        echo $returnData;
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

    define("HEURIST_DB_DESCRIPTOR_RECTYPE", 21); // the record type for database descriptor records, hardcodes in this database

	// allocate a new user for this database unless the user's email address is recognised
	// If a new user, log the user in and assign the record ownership to that user


	// Find the registering user in the index database, make them the owner of the new record
	$usrEmail = strtolower(trim($usrEmail));
	$res = mysql_query("select ugr_ID, ugr_Name, ugr_Password, ugr_FirstName, ugr_LastName from sysUGrps where lower(ugr_eMail)='".$usrEmail."'");
	$indexdb_user_id = null;
	if($res) { // query OK
		if(mysql_num_rows($res) == 0) { // did not find the user, create a new one and pass back login info
			$res = mysql_query("insert into sysUGrps (`ugr_Name`, `ugr_Password`, `ugr_eMail`, `ugr_Enabled`, `ugr_FirstName`, `ugr_lastName`)
			VALUES  ('$usrName','$usrPassword','$usrEmail','y','$usrFirstName','$usrLastName')");
			if($res) { 	// New user created successfully
				$indexdb_user_id = mysql_insert_id();
				header('Location: ' . HEURIST_BASE_URL . '/common/connect/login.php?db=' . HEURIST_DBNAME . (isset($last_uri) ? '&last_uri=' . urlencode($last_uri) : '')); // TODO: Change to HEURIST_BASE_URL
			} else { // Unable to create the new user
                $error = "Unable to write new user in Heurist master index database\n" . "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
                $returnData = $dbID . "," . $error;
                echo $returnData; // if you can't set up user it isn't worth trying to register the database''
			}
		} else { // existing user
		$row = mysql_fetch_row($res);
		$indexdb_user_id = $row[0]; // set the user ID for the user in the index database, everything else is known
		}

	} else {// error trying to find usergroup in UGrps table
		$error = "Unable to execute search for user in Heurist master index database\n" . "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
        $returnData = $dbID . "," . $error;
        echo $returnData; // if you can't set up user it isn't worth trying to register the database''
	}

	// write the core database record describing the database to be registered and allocate registration ID
	// First look to see if ther is an existing registration - note, this uses the URL to find the record, not the registration ID
	$res = mysql_query("select rec_ID, rec_Title from Records where `rec_URL`='$serverURL'");
	if(mysql_num_rows($res) == 0) { // new registration
		 $res = mysql_query("insert into Records
		 (rec_URL, rec_Added, rec_Title, rec_RecTypeID, rec_AddedByImport, rec_OwnerUGrpID, rec_NonOwnerVisibility,rec_Popularity)
		 VALUES  ('$serverURL', now(), '$dbTitle', " . HEURIST_DB_DESCRIPTOR_RECTYPE . ", 0, $indexdb_user_id, 'viewable', 99)");
	    if (!$res) { // Unable to allocate a new ID
	        $error = "Unable to write record in Heurist master index database\n" . "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
	        $returnData = $dbID . "," . $error;
	        echo $returnData;
		} else { // core database record created OK
	        $dbID = mysql_insert_id();
	        $returnData = $dbID;
	    }
    } else { // existing registration - note that it updates the record based on its URL, not itse registration ID
        // TO DO: we should really check the registration ID b/c someone could update a different registration with the
        // same URL and then the update might go there instead of to the registration record for the desired DB
        // TO DO: We should make rec_URL a UNIQUE index (???) so no two records can point to the same URL - but this woyuld
        // cause a problem if a hidden record already pointed to a URL and someone tries to add a record referencing it
	    $row = mysql_fetch_row($res);
		$res = mysql_query("update Records set `rec_Title`='$dbTitle' where `rec_ID`='".$row[0]."'");
		if(!mysql_error()) {
			$returnData = -1 . ", Description succesfully changed";
		} else {
			error_log('ERROR: '.mysql_error()); // TODO: Fix database not to give this error
			$returnData = 0 . ", An error occurred while trying to change the database description";
		}
	}

	echo $returnData;
?>