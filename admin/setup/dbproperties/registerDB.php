<?php

/**
* registerDB.php - Registers the current database with Heurist_Master_Index database, 
* on the Heurist master server (heurist.sydney.edu.au as at 2016), stores
* metadata in the index database, sets registration code in sysIdentification table.
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');
require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');


if (isForOwnerOnly("to register a database with the Heurist master index")){
    return;
}

$sError = null;

$user_id = get_user_id();

/*  WHY HAS THIS BEEN COMMENTED OUT ?????? -
// User must be system administrator or admin of the owners group for this database
if (!is_admin()) {
$sError = "You must be logged in as system administrator to register a database";
} else  if (get_user_id() != 2) {
$sError = "Only the owner/creator of the database (user #2) may register the database. ".
"<br/><br/>This user will also own (and be able to edit) the registration record in the Heurist master index database";
return;
}
*/

mysql_connection_insert(DATABASE); // Connect to the current database (the one being registered)

// Look up current user email from sysUGrps table in the current database (the one being registered)
// Registering user must be a real user so that there is an email address and password to attach to the registration record.
// which rules out using the Database Managers group. Since other users will be unable to login and edit this record, it's better
// to only allow the creator (user #2) to register the db, to avoid problems down the track knowing who registered it.

$res = mysql_query("select ugr_eMail, ugr_Password,ugr_Name,ugr_FirstName,ugr_LastName from sysUGrps where `ugr_ID`='$user_id'");

if(mysql_num_rows($res) == 0) {
    $sError = "Warning<br/><br/>Unable to read your email address from user table";
}else{
    $row = mysql_fetch_row($res);
    $usrEmail = $row[0]; // Get the current user's email address from UGrps table
    $usrPassword = $row[1];
    $usrName = $row[2];
    $usrFirstName = $row[3];
    $usrLastName = $row[4];

    if(!$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword){
        $sError = "Warning<br/><br/>Please edit your user profile to specify your full name, ".
        "login and email address before registering this database";
    }
}

if($sError){
    print "<html>";
    print "<head>";
    print '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    print "<link rel=stylesheet href='../../../common/css/global.css'></head>".
    "<body><div class=wrap><div id=errorMsg><span>$sError</span>".
    "<p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
    " target='_top'>Log out</a></p></div></div></body></html>";
    return;
}

?>

<link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
<link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">
<link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>Register Database with Heurist Master Index</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
    </head>

    <script type="text/javascript">
        function hideRegistrationForm() {
            document.getElementById("registerDBForm").style.display = "none";
        }
        
        
        function onKeyUpDbDescription( event ){
            var len = event.target.value.length;
            var btn = document.getElementById('btnSubmit');
            var lbl = document.getElementById('cntChars');
            btn.disabled = (len<40); 
            btn.style.color = (len<40)?'lightgray':'black';

            lbl.innerHTML = len+' characters';            
            lbl.style.color = (len<40)?'red':'#6A7C99';
        }
    </script>

    <!-- Database registration form -->

    <body class="popup">
        <?php
        if(strpos(HEURIST_BASE_URL, '//localhost')>0 ||  strpos(HEURIST_BASE_URL, '//127.0.0.1')>0){
            echo '<p class="error">Impossible to register database running on local server '.HEURIST_BASE_URL.'</p></body></html>';
            exit();
        }
        ?>

        <div class="banner"><h2>Register Database with Heurist Master Index</h2></div>
        <div id="page-inner" style="overflow:auto">
            <h3>Registration</h3>
            <div id="registerDBForm" class="input-row" style="margin-top: 20px;">
                <form action="registerDB.php" method="POST" name="NewDBRegistration">
                    <div class='input-header-cell'><b>Database Description</b></div><div class='input-cell'>
                        <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                        <div style="display:inline-block">
                            <textarea  type="memo" maxlength="1000" cols="80" rows="3" name="dbDescription"
                                onkeyup="onKeyUpDbDescription( event )"></textarea>
                        </div>
                        <div  style="display:inline-block">
                            <label id="cntChars" style="text-align:left"></label><br/>
                            <input id="btnSubmit" type="submit" name="submit" value="Register" style="font-weight: bold;color:lightgray" onClick="hideRegistrationForm()" disabled="disabled" >
                        </div>
                        <div>Enter a short but informative description (minimum 40 characters) of this database (displayed in search list)</div>
                        <div  style="margin-top: 15px; margin-bottom: 20px;">
                            <br/>Note: After registering the database, you will be asked to log in to a Heurist database (Heurist_Master_Index).
                            <br/>You should log into this database using your email address and the same login as your current database
                            <br/>(or the first database you registered, if different). This will allow you to edit the collection metadata
                            <br/>describing your database.
                        </div>
                    </div>
                </form>
            </div>

            <?php



            $res = mysql_query("select sys_dbRegisteredID, sys_dbName, sys_dbDescription, sys_OwnerGroupID ".
                "from sysIdentification where 1");

            // Start by hiding the registration/title edit form
            echo '<script type="text/javascript">';
            echo 'document.getElementById("registerDBForm").style.display = "none";';
            echo '</script>';

            if (!$res) { // Problem reading current registration ID
                $msg = "Unable to read database identification record. This database might be incorrectly set up. \n" .
                "Please contact <a href=mailto:info@HeuristNetwork.org>Heurist developers</a> for advice";
                echo $msg . "<br />";
                return;
            }

            $row = mysql_fetch_row($res); // Get system information for current database
            $dbID = $row[0];
            $dbName = $row[1];
            $dbDescription = $row[2];
            $ownerGrpID = $row[3];

            // Check if database has already been registered

            if (isset($dbID) && ($dbID != 0))
            { // already registered, display info and link to Heurist_Master_Index edit
                echo '<script type="text/javascript">';
                echo 'document.getElementById("registerDBForm").style.display = "none";';
                echo '</script>';
                echo "<div class='input-row'><div class='input-header-cell'>Database:</div><div class='input-cell'>".DATABASE." </div></div>";
                echo "<div class='input-row'><div class='input-header-cell'>Already registered with</div>".
                "<div class='input-cell'><b>ID:</b> " . $dbID . " </div></div>";
                echo "<div class='input-row'><div class='input-header-cell'>Description:</div>".
                "<div class='input-cell'>". $dbDescription . "</div></div>";
                $url = HEURIST_INDEX_BASE_URL."?fmt=edit&recID=".$dbID."&db=Heurist_Master_Index";
                echo "<div class='input-row'><div class='input-header-cell'><b>Please edit the collection metadata ".
                "describing this database:</b></div><div class='input-cell'>".
                "<a href=$url target=_blank style='color:red;'>Click here to edit</a> (login as person who registered this database - ".
                " note: use EMAIL ADDRESS as username)</div></div>";
            } // existing registration
            else
            { // New registration, display registration form
                echo '<script type="text/javascript">';
                echo 'document.getElementById("registerDBForm").style.display = "block";';
                echo '</script>';
            } // new registration

            // Do the work of registering the database if a suitable title is set

            if(isset($_POST['dbDescription'])) {

                if(strlen($_POST['dbDescription']) > 39 && strlen($_POST['dbDescription']) < 1000) {
                    $dbDescription = $_POST['dbDescription'];
                    echo '<script type="text/javascript">';
                    echo 'document.getElementById("registerDBForm").style.display = "none";';
                    echo '</script>';
                    registerDatabase(); // this does all the work of registration
                } else {
                    echo "<p style='color:red;font-weight:bold'>The database description should be an informative description ".
                    "of the content, of at least 40 characters (max 1000)</p>";
                }
            }

            ?>

            <!-- Explanation for the user -->

            <div class="separator_row" style="margin:20px 0;"></div>
            <h3>Suggested workflow for new databases:</h3>

            <p>After creating a new database, please use the links: </p>
            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Database &gt; <u>Manage structure</u></div>
                <div class="detail">Modify the record types and fields describing them, and add new record types, fields and terms</div>
            </div>

            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Database &gt; <u>Acquire from templates</u></div>
                <div class="detail">Browse and download record type definitions documented on the Heurist network web site. <br />
                    In addition to saving a great deal of time, the documentation helps you learn about the best ways of setting up Heurist structures.<br />
                    The record type definitions downloaded can later be modified to your requirements.</div>
            </div>
            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Database &gt; <u>Acquire from database</u></div>
                <div class="detail">Find and download common record type definitions from any registered Heurist database, including the specially
                    curated databases set up by the Heurist developers. <br />The record type definitions downloaded can later be modified
                    to your requirements.</div>
            </div>

            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Database &gt;<u>Properties</u></div>
                <div class="detail">Set information about the database, including ownership and some general behaviours</div>
            </div>

            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Database &gt; <u>Register</u></div>
                <div class="detail">Register the database with the Heurist Master Index.<br />
                    This will give your database a unique code and allow Heurist to check for new versions of
                    the software and database formats. <br/>It also allows other databases to import structural elements
                    (record types, field types and terms) but does NOT confer any form of access to data in this database</div>
            </div>

            <?php

            function registerDatabase() {
                $heuristDBname = rawurlencode(HEURIST_DBNAME);
                global $dbID, $dbName, $ownerGrpID, $indexdb_user_id, $usrEmail, $usrPassword,
                $usrName, $usrFirstName, $usrLastName, $dbDescription;

                $serverURL = HEURIST_BASE_URL . "?db=" . $heuristDBname;
                $serverURL = substr($serverURL,7); // to avoid conversion path to localhost if masterindex on the same server

                $usrEmail = rawurlencode($usrEmail);
                $usrName = rawurlencode($usrName);
                $usrFirstName = rawurlencode($usrFirstName);
                $usrLastName = rawurlencode($usrLastName);
                $usrPassword = rawurlencode($usrPassword);
                $dbDescriptionEncoded = rawurlencode($dbDescription);

                $reg_url =   HEURIST_INDEX_BASE_URL  . "admin/setup/dbproperties/getNextDBRegistrationID.php" .
                "?db=Heurist_Master_Index&dbReg=" . $heuristDBname . "&dbVer=" . HEURIST_DBVERSION .
                "&dbTitle=" . $dbDescriptionEncoded . "&usrPassword=" . $usrPassword .
                "&usrName=" . $usrName . "&usrFirstName=" . $usrFirstName .
                "&usrLastName=" . $usrLastName . "&usrEmail=".$usrEmail
                ."&serverURL=" . rawurlencode($serverURL);

                //$data = loadRemoteURLContent($reg_url);
                $data = loadRemoteURLContentSpecial($reg_url); //without proxy


                if (!$data) {
                    die("Unable to contact Heurist master index, possibly due to timeout or proxy setting<br />".
                        "URL requested: <a href='$reg_url'>$reg_url</a>");
                }

                if ($data) {
                    $dbID = intval($data); // correct return of data is just the registration number. we probably need a
                    // better formatted return with some tags to ensure we are getting the right thing
                }

                if ($dbID == 0) { // Unable to allocate a new database identifier
                    $decodedData = explode(',', $data);

                    if(count($decodedData)>0){
                        $msg = $decodedData[1];
                    }else{
                        $msg = "Problem allocating a database identifier from the Heurist master index, " .
                        "returned the following instead of a registration number:\n" . substr($data, 0, 25) .
                        " ... \nPlease contact <a href=mailto:info@HeuristNetwork.org>Heurist developers</a> for advice";
                    }
                    echo $msg . "<br />";
                    return;
                }
                else if($dbID == -1)
                { // old title update function, should no longer be called
                    $res = mysql_query("update sysIdentification set `sys_dbDescription`='".mysql_real_escape_string($dbDescription)."' where 1");
                    echo "<div class='input-row'><div class='input-header-cell'>".
                    "Database description (updated):</div><div class='input-cell'>". $dbDescription."</div></div>";
                } else
                { // We have got a new dbID, set the assigned dbID in sysIdentification
                    mysql_connection_insert(DATABASE);
                
                    $res = mysql_query("update sysIdentification set `sys_dbRegisteredID`='$dbID', ".
                        "`sys_dbDescription`='".mysql_real_escape_string($dbDescription)."' where 1");
                    if($res) {
                        echo "<div class='input-row'><div class='input-header-cell'>Database:</div>" .
                        "<div class='input-cell'>".DATABASE."</div></div>";
                        echo "<div class='input-row'><div class='input-header-cell'>".
                        "Registration successful, database ID allocated is</div>".
                        "<div class='input-cell'>" . $dbID . "</div></div>";
                        echo "<div class='input-row'><div class='input-header-cell'></div>".
                        "<div class='input-cell'>Basic description: " . $dbDescription . "</div></div>";
                        $url = HEURIST_INDEX_BASE_URL."?fmt=edit&recID=".$dbID."&db=Heurist_Master_Index";
                        echo "<div class='input-row'><div class='input-header-cell'>Collection metadata:</div>".
                        "<div class='input-cell'><a href=$url target=_blank>Click here to edit</a> " .
                        "(login - if asked - as yourself) </div></div>";
                        // Update original DB ID and original db code for all existing record types, fields and terms
                        // which don't have them (meaning that they were defined within this database)
                        // Record types
                        $result = db_register(DATABASE, $dbID);
                        if (!$result) {
                            echo "<div class=wrap><div id=errorMsg>Unable to set all values for originating DB information for ".DATABASE.
                            " - one of the update queries failed</div></div>";
                        }
                        ?>
                        <script> // automatically call Heurist_Master_Index metadata edit form for this database
                            window.open("<?=$url?>",'_blank');
                        </script>
                        <?php
                    } else {
                        $msg = "<div class=wrap><div id=errorMsg><span>Unable to write database identification record</span>".
                        "this database might be incorrectly set up<br />".
                        "Please contact <a href=mailto:info@HeuristNetwork.org>Heurist developers</a> for advice</div></div>";
                        echo $msg;
                        return;
                    } // unable to write db identification record
                } // successful new DB ID
            } // registerDatabase()

            ?>


        </div>
    </body>
</html>