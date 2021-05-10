<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* returns new id  or 0,error messahe
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


// TO DO: WE NEED SOME MECHANISM TO AVOID DENIAL OF SERVICE ATTACK WHICH REPEATEDLY REQUESTS REGISTRATIONS

// TODO: We may need to hobble/delete some of the functionality on Heurist_Master_Index to avoid people
// creating unwanted records or importing random crap into it

if(@$_REQUEST["db"]!='Heurist_Master_Index'){
    echo '0,This script allowed for Master Index database only';
    return;
}

    require_once (dirname(__FILE__).'/../../../hsapi/System.php');
    require_once(dirname(__FILE__).'/../../../hsapi/utilities/utils_mail.php');

    $connect_failure = false;
    
if(isset($system)){ //this is call from heurist server by include  loadRemoteURLContentSpecial->getScriptOutput
    
    $mysqli = $system->get_mysqli();
    $connect_failure = (mysql__usedatabase($mysqli, @$_REQUEST['db'])!=true);

}else{ //this is call from remote server as independent script
    
    // init main system class
    $system = new System();
    
    $connect_failure = (!$system->init(@$_REQUEST['db'], false));
    
}

if($connect_failure){
        //$response = $system->getError();
        //echo '0,'.$response[0]['message'];
        echo '0, Failed to connect to  Master Index database';
        return;
}
    
    

$indexdb_user_id = 0; // Flags problem if not reset

// Get parameters passed from registration request
// @ preceding $_REQUEST avoids errors, sets Null if parameter missing
$serverURL = $_REQUEST["serverURL"];
$serverURL_lc = strtolower($_REQUEST["serverURL"]);
$dbReg = $_REQUEST["dbReg"];
$dbTitle = $_REQUEST["dbTitle"];
$dbVersion = @$_REQUEST["dbVer"];
$usrEmail = $_REQUEST["usrEmail"];
$usrPassword = $_REQUEST["usrPassword"];
$usrName = $_REQUEST["usrName"];
$usrFirstName = $_REQUEST["usrFirstName"];
$usrLastName = $_REQUEST["usrLastName"];
$newid = @$_REQUEST["newid"];

// $var is null, blank, 0 or false --> false
if (!$serverURL || !$dbReg || !$dbTitle || !$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword) { // error in one or more parameters
    $returnData = '0,Bad parameters passed';
    echo $returnData;
    return;
}

if(strpos($serverURL_lc,'http://')===false && strpos($serverURL_lc,'https://')===false){
    $serverURL = 'http://'.$serverURL;
    $serverURL_lc = strtolower($serverURL);
}

if(strpos($serverURL_lc, '//localhost')>0 ||  strpos($serverURL_lc, '//127.0.0.1')>0){
    echo '0,Impossible to register database from local server '.$serverURL;
    return;
}

define("HEURIST_DB_DESCRIPTOR_RECTYPE", 22); // the record type for database (collection) descriptor records - fixed for Master database

if($newid>0){ 

    if(!(strpos($serverURL_lc, HEURIST_MAIN_SERVER)===0
        || strpos($serverURL_lc, 'http://heurist.sydney.edu.au')===0)){ 
    
        echo '0,It is possible to assign arbitrary ID for databases on heurist servers only';
        return;
    }
    
    $rec_id = mysql__select_value($mysqli, 'select rec_ID from Records where rec_ID='.$newid);
    
    if($rec_id>0){
        echo '0,Database ID '.$newid.' is already allocated. Please choose different number';
        return;
    }
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


// allocate a new user for this database unless the user's email address is recognised
// If a new user, log the user in and assign the record ownership to that user
// By allocating users on the database based on email address we can allow them to edit their own registrations
// but they can't touch anyone else's

$mysqli = $system->get_mysqli();

// Find the registering user in the index database, make them the owner of the new record
$usrEmail = strtolower(trim($usrEmail));

$indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_eMail)="'.$usrEmail.'"');

// Check if the email address is recognised as a user name
// Added 19 Jan 2012: we also use email for ugr_Name and it must be unique, so check it has not been used
if(!($indexdb_user_id>0)) { // no user found on email, try querying on user name
    $indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_Name)="'.$usrEmail.'"');
}

if(!($indexdb_user_id>0)) { // did not find the user, create a new one and pass back login info
    
    // Note: we use $usrEmail as user name because the person's name may be repeated across many different users of
    // different databases eg. there are lots of johnsons, which will cause insert statement to fail as ugr_Name is unique.
    
    $indexdb_user_id = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr_', 
        array(
            'ugr_Name'=>$usrEmail,
            'ugr_Password'=>$usrPassword,
            'ugr_eMail'=>$usrEmail,
            'ugr_Enabled'=>'y',
            'ugr_FirstName'=>$usrFirstName,
            'ugr_LastName'=>$usrLastName,
        )
    );
    
    if(!($indexdb_user_id>0)) { // Unable to create the new user
        echo '0,Unable to write new user in Heurist master index database<br>'.
            'Please '.CONTACT_HEURIST_TEAM.' for advice';   
        return;
    }

}

    // TODO: It seems like the user ID is not being set properly,
    //       at least that seems to be indicated by the fact that the mailout comes with indexdb_user_id=0



// write the core database record describing the database to be registered and allocate registration ID
// This is not a fully valid Heurist record, we let the edit form take care of that
// First look to see if there is an existing registration - note, this uses the URL to find the record, not the registration ID
// TODO: Would be good to have a recaptcha style challenge otherwise can be called repeatedly
// with slight URL variations to spawn multiple registrations of dummy databases

$dbID = mysql__select_value($mysqli, "select rec_ID from Records where lower(rec_URL)='$serverURL_lc'");

if($dbID>0) { 
    
    echo $dbID;
    
}else{// new registration

    $mysqli->query('set @logged_in_user_id = 2');

    $dbID = mysql__insertupdate($mysqli, 'Records', 'rec_', 
        array(
            'rec_ID'=>($newid>0)?-$newid:0,
            'rec_URL'=>$mysqli->real_escape_string($serverURL),
            'rec_Added'=>date('Y-m-d H:i:s'),
            'rec_Title'=>$mysqli->real_escape_string($dbTitle),
            'rec_RecTypeID'=> HEURIST_DB_DESCRIPTOR_RECTYPE,
            'rec_AddedByImport'=>0,
            'rec_OwnerUGrpID'=>$indexdb_user_id,
            'rec_NonOwnerVisibility'=>'public',
            'rec_Popularity'=>99,
        ), true
    );
    $mysqli->query('set @logged_in_user_id = '.$system->get_user_id());
    
    if($dbID>0){
        
        $system->defineConstant('DT_NAME');

        //Write the database title into the details, further data will be entered by the Heurist form
        mysql__insertupdate($mysqli, 'recDetails', 'dtl_', 
            array(
                'dtl_RecID'=>$dbID,
                'dtl_DetailTypeID'=>DT_NAME,
                'dtl_Value'=>$mysqli->real_escape_string($dbTitle)
            )
        );

        //Write db version as detail
        if($dbVersion){
            mysql__insertupdate($mysqli, 'recDetails', 'dtl_', 
                array(
                    'dtl_RecID'=>$dbID,
                    'dtl_DetailTypeID'=>335,
                    'dtl_Value'=>$dbVersion
                )
            );
        }

        // Write the record bookmark into the bookmarks table. This allos the user registering the database
        // to see thir lsit of databases as My Bookmarks
        mysql__insertupdate($mysqli, 'usrBookmarks', 'bkm_', 
            array(
                'bkm_UGrpID'=>$indexdb_user_id,
                'bkm_RecID'=>$dbID
            )
        );
        

        //send email to administrator about new database registration
        $email_text =
        "There is a new Heurist database registration on the Heurist Master Index\n\n".
        "Database Title:     ".htmlspecialchars($dbTitle, ENT_QUOTES, 'UTF-8')."\n".
        "Registration ID:    ".$dbID."\n". // was $indexdb_user_id, which is always 0 b/cnot yet logged in to master index
        "DB Format Version:  ".$dbVersion."\n\n".
        // "User name:    ".$usrFirstName." ".$usrLastName."\n".  // comes out 'every user' b/c user not set
        // "Email address: ".$usrEmail."\n".                      // comes out 'not set for user 0'
        "Go to the address below to review the database:\n".
        $serverURL;

        $dbowner = user_getDbOwner($mysqli);
        $dbowner_Email = $dbowner['ugr_eMail'];
        $email_title = 'Database registration ID: '.$dbID.'. User ['.$indexdb_user_id.']';

        sendEmail($dbowner_Email, $email_title, $email_text, null);
        //END email -----------------------------------

        
        echo $dbID;
    }else{
        
        $system->addError(HEURIST_DB_ERROR, 'Cannot write record in Heurist master index ', $dbID);
        
        $error = 'Cannot write record in Heurist master index database<br>'
        .'The URL may have been registered with a previous database.<br>'
        .'Please '.CONTACT_HEURIST_TEAM.' for advice';
        echo '0,'. $error;
    }
}
?>
