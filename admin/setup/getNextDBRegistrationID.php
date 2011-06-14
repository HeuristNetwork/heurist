<?php

	/**
	* getNextRegistrationID.php - request an ID from HeuristScholar.org/db=HeuristIndex, allocates the ID,
	* sets metadata in record and details, Ian Johnson 18 Jan 2011. ONLY ALLOW IN HEURISTSCHOLAR.ORG index database
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
    mysql_connection_db_insert("HeuristSystem_Index");

    $ownerGrpID = 0; // Flags problem if not reset
    $returnData = ''; // String returned to caller, contains dbID or 0, and error message (if any)
    
    // Get parameters passed from registration request
    // @ preceding $_REQUEST avoids errors, sets Null if parameter missing
	$serverURL = $_REQUEST["serverURL"];
	$dbReg = $_REQUEST["dbReg"];
	$dbTitle = $_REQUEST["dbTitle"];
	$ownerGrpEmail = $_REQUEST["ownerGrpEmail"];

    // $var is null, blank, 0 or false --> false
	if (!$serverURL || !$dbReg || !$dbTitle || !$ownerGrpEmail) { // error in one or more parameters
        $returnData = '0,Bad parameters passed';
        echo $returnData;
    }

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
 
    define("HEURIST_DB_DESCRIPTOR_RECTYPE", 209); // the record type for database descriptor records
	
	// allocate a new user for this database unless the sysadmin password is recognised
	// If a new user, log the user in and assign the record ownership to that user
	// If an existing user, get them to login otherwise someone could gain access to their records

	$ownerGrpEmail = strtolower(trim($ownerGrpEmail));
	$res = mysql_query("select ugr_ID from sysUGrps where lower(ugr_eMail)='".$ownerGrpEmail."'");
	if($res) { // query OK
		if(mysql_num_rows($res) == 0) { // did not find the user, create a new one and pass back login info
			$pwd = genRandomString();
			$res = mysql_query("insert into sysUGrps (`ugr_Name`, `ugr_Password`, `ugr_eMail`, `ugr_Enabled`) VALUES  ('$ownerGrpEmail','$pwd','$ownerGrpEmail','Yes')");
			if($res) {
				$ownerGrpID = mysql_insert_id();
                header('Location: ' . HEURIST_BASE_URL . '/common/connect/login.php?db=' . HEURIST_DBNAME . (isset($last_uri) ? '&last_uri=' . urlencode($last_uri) : '')); // TODO: Change to HEURIST_BASE_URL
			} else { // Unable to create the new user
                $error = "Unable to write new user in Heurist master index database\n" . "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
                $returnData = $dbID . "," . $error;
                echo $returnData; // if you can't set up user it isn't worth trying to register the database''
			}
		}
	} else {// error trying to find usergroup in UGrps table
		$error = "Unable to execute search for user in Heurist master index database\n" . "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
        $returnData = $dbID . "," . $error;
        echo $returnData; // if you can't set up user it isn't worth trying to register the database''
	}

	// write the core database record describing the database to be registered and allocate registration ID
	$res = mysql_query("select rec_ID, rec_Title from Records where `rec_URL`='$serverURL'");
	if(mysql_num_rows($res) == 0) {
		$res = mysql_query("insert into Records (rec_URL, rec_Added, rec_Title, rec_RecTypeID, rec_AddedByImport, rec_OwnerUGrpID, rec_NonOwnerVisibility) VALUES  ('$serverURL', now(), '$dbTitle', " . HEURIST_DB_DESCRIPTOR_RECTYPE . ", 0, 1, 'Visible')");
	    if (!$res) { // Unable to allocate a new ID
	        $error = "Unable to execute search for user in Heurist master index database\n" . "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
	        $returnData = $dbID . "," . $error;
	        echo $returnData;
		} else { // core database record created OK
	        $dbID = mysql_insert_id();
	        $returnData = $dbID;
	    }
    } else {
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