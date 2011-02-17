<?php

    /**
    * registerDB.php - registers the current database with HeuristScholar.org/db=HeuristIndex , stores
    * metadata in the index database, sets registration code in sysIdentification table. Ian Johnson 17 Jan 2011.
    * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
    * @link: http://HeuristScholar.org
    * @license http://www.gnu.org/licenses/gpl-3.0.txt
    * @package Heurist academic knowledge management system
    * @todo
    **/

?>
<html>
<head><title>Heurist database registration for db = ??</title></head>
<body>
Registering database ...
</body>    

<?php

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/getNextDBRegistrationID.php');

if (!is_logged_in()) {
    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
    return;
}

// User must be system administrator or admin of the owners group for this database
if (!is_admin()) {
    print "<html><body><p>You must be logged in as system administrator to register a database</p><p><a href=" .
        HEURIST_URL_BASE . "common/connect/login.php?logout=1&amp;db=" . HEURIST_DBNAME .
        "'>Log out</a></p></body></html>";
    return;
}

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

mysql_connection_db_select(DATABASE); // Connect to the current database

// Check if already registered and exit if so, otherwise request registration from Heurist Index database

$res = mysql_query("select sys_dbRegisteredID,sys_dbName,sys_dbDescription,sys_OwnerGroupID from sysIdentification where sys_ID=1");

if (!$res) { // Problem reading current registration ID
    $msg = "Unable to read database identification record, your database might be incorrectly set up. \n" .
    "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice.";
    print "<script>alert('" . $msg . "');</script>\n";
    return;
}

$row = mysql_fetch_row($res); // get system information for current database

$DBID=$row[0];
$dbName=$row[1];
$dbDescription=$row[2];
$ownerGrpID=$row[3];


// Look up owner group sysadmin password from sysUGrps table

// TO DO: THIS ONLY WORKS IF THE sysUGrps table is in the current database, need to allow
// for it being deferred to the central Heursit database on the installation or to another HDB
$res = mysql_query("select ugr_eMail from sysUGrps where ugr_ID=$ownerGrpID");

/*  
THIS TEST IS CAUSING SCRIPT TO RETURN NOTHING
 if (!$res) { // Problem reading owner group email address
    msg = "non-critical warning: Unable to read database owners group email, not currently supporting deferred users database";
    print "<script>alert('" . $msg . "');</script>\n";
    $ownerGrpEmail="";
} else { 
*/
    $row = mysql_fetch_row($res); $ownerGrpEmail=$row[0]; // get owner group email address from UGrps table
/* 
} 
*/


// Check if database has already been registered

if (!$DBID == 0) { // already registered
    $msg = "Your database is already registered with ID = $DBID";
    print "<script>alert('" . $msg . "');</script>\n";
    return;
}

// Not yet registered, pass descriptive information and ask for new registration ID


if ($DBID==0) { // not yet registered
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //return curl_exec output as string
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);    //don't include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // follow server header redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // don't verify peer cert
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);    // timeout after ten seconds
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);    // no more than 5 redirections
    curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
    $reg_url =  HEURIST_INDEX_BASE_URL . "admin/setup/getNextDBRegistrationID.php?db=index" .
                "&serverURL=" . HEURIST_BASE_URL . "&dbReg=" . HEURIST_DBNAME . 
                "&dbTitle=" . HEURIST_DBNAME . " - enter title here ...&ownerGrpEmail=$ownerGrpEmail";
    curl_setopt($ch, CURLOPT_URL,$reg_url);
    $data = curl_exec($ch);
    $error = curl_error($ch);
    if ($error) {
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        error_log("$error ($code)" . " url = ". $_REQUEST['reg_url']);
    } else {   
        // parse $data here $DBID = intval($data); // IS THIS THE RIGHT FUNCTION ????????
    }
     if ($DBID == 0) { // unable to allocate a new database identifier
        $msg = "Problem allocating a database identifier from the Heurist index.\n" .
        "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
        print "<script>alert('" . $msg . "');</script>\n";
        return;
    }  

    // We have got a new dbID, set the assigned dbID in sysIdentification

    $query = "update sysIdentification set sys_dbRegisteredID=$DBID where sys_ID=1";
    $res = mysql_query($query);
   
    /*  this test is causing script to return nothing
    if ($res) {
        $msg = "Registration successful, database ID allocated is $DBID\n" .
        "Please fill in full registration details on the following form, \n".
        "You will need to log in to the HeuristIndex database with user name $ownerGrpEmail";
        // print "<script>alert('" . $msg . "');</script>\n";
        // TO DO; NEED TO CHANGE 'instance' --> 'db' and 'bib_id' --> 'RecID'
        header('Location: ' . HEURIST_BASE_URL . '/records/edit/editRecord.html?bib_id=$dbID&instance=HeuristIndex';
        return;
    } 
   
    else {
       $msg = "Unable to write database identification record, your database might be incorrectly set up\n".
                "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
        print "<script>alert('" . $msg . "');</script>\n";
        return;
    }
    */
  
  }



?>
       

       
 </html>
    
    
   
    
