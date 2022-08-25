<?php
//@TODO to hclient

/**
* registerDB.php - Registers the current database with Heurist Reference Index database (HEURIST_INDEX_DATABASE), 
* on the Heurist master server (HEURIST_MAIN_SERVER), stores
* metadata in the index database, sets registration code in sysIdentification table.
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

define('MANAGER_REQUIRED', 1);   
define('PDIR','../../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');

$sysadmin_pwd = @$_REQUEST['pwd'];

if(!$system->is_dbowner() && $system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions) ){
    $err = $response = $system->getError();
    print @$err['message'];
    exit();
}

if(strpos(HEURIST_BASE_URL, '//localhost')>0 ||  strpos(HEURIST_BASE_URL, '//127.0.0.1')>0){

    $message = 'Impossible to register database running on local server '.HEURIST_BASE_URL;
    include dirname(__FILE__).'/../../../hclient/framecontent/infoPage.php';    
    exit();
}

$mysqli = $system->get_mysqli();

$dbowner = user_getDbOwner($mysqli);

if(!$dbowner['ugr_eMail'] || !$dbowner['ugr_FirstName'] || !$dbowner['ugr_LastName'] || !$dbowner['ugr_Name'] ){
    
    header('Location: '.ERROR_REDIR.'&msg='.rawurlencode( 
        'Warning<br/><br/>Please edit your user profile to specify your full name, login and email address before registering this database'     )); 
    exit();
}
?>
<html>

    <head>
        <title>Register Database with Heurist Reference Index</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>        
    </head>

    <script type="text/javascript">
        function hideRegistrationForm() {
            var ele = document.getElementById("registerDBForm");
            if(ele) ele.style.display = "none";
        }
        
        function onKeyUpDbDescription( event ){
            var ele = document.getElementById('dbDescription');
            if(ele){
                var len = ele.value.length; //event.target
                var btn = document.getElementById('btnSubmit');
                var lbl = document.getElementById('cntChars');
                btn.disabled = (len<40); 
                btn.style.color = (len<40)?'lightgray':'black';

                lbl.innerHTML = len+' characters';            
                lbl.style.color = (len<40)?'red':'#6A7C99';
            }
        }
    </script>
    <style>
    .detail{
        max-width: 1000;
    }
    .detailRow{
        padding: 10px 0;
        height: 2.5em;
    }
    </style>
    

    <!-- Database registration form -->

    <body class="popup" onload="onKeyUpDbDescription()">
        <!-- <div class="banner"><h2>Register Database with Heurist Reference Index</h2></div> -->
        <div id="page-inner" style="overflow-y: auto;overflow-x: hidden;">
            <!-- <h3>Registration</h3> -->
<?php

            $sysinfo = $system->get_system(); //get info from sysIdentification

            $dbID = $sysinfo['sys_dbRegisteredID'];
            $dbName = $sysinfo['sys_dbName'];
            $dbDescription = $sysinfo['sys_dbDescription'];
            $ownerGrpID = $sysinfo['sys_dbOwner'];
            $dbNewID = @$_POST['dbNewID'];

            $isAlreadyRegistred = isset($dbID) && ($dbID > 0);
            $isRegistrationInProgress = isset($_POST['dbDescription']);
            
            // Do the work of registering the database if a suitable title is set
            if( $isRegistrationInProgress ) {
                
                $dbDescription = $_POST['dbDescription'];
                
                if(strlen($dbDescription) > 39 && strlen($dbDescription) < 1000) {
                    
                } else {
                    echo "<p style='color:red;font-weight:bold'>The database description should be an informative description ".
                    "of the content, of at least 40 characters (max 1000)</p>";
                    $isRegistrationInProgress = false;
                }
                
                if($dbNewID!=null && $dbNewID!=''){
                                            
                    if($dbNewID>0 && $dbNewID<4294967295) {
                        
                        if($system->verifyActionPassword(@$_REQUEST['dbNewID_pwd'], $passwordForServerFunctions)){
                            
                            $err = $response = $system->getError();
                            
                            print "<p style='color:red;font-weight:bold'>Is is not possible to assign database ID. "
                                .@$err['message']."</p>";
                            $isRegistrationInProgress = false;
                        }
                        
                    }else{
                        echo "<p style='color:red;font-weight:bold'>The database ID must be positive number</p>";
                        $isRegistrationInProgress = false;
                    }
                }
            }


            if($isRegistrationInProgress){
                
                $isRegistrationInProgress = registerDatabase(); // this does all the work of registration
                
            // Check if database has already been registered
            }else if ($isAlreadyRegistred)
            { // already registered, display info and link to HEURIST_INDEX_DATABASE edit
            
                $edit_url = HEURIST_INDEX_BASE_URL.'?fmt=edit&recID='.$dbID.'&db='.HEURIST_INDEX_DATABASE;
?>
    <fieldset>
    
    <div>
        <div class="header"><label>Database:</label></div>
        <div class="text ui-widget-content ui-corner-all" style="margin:4px"><?php echo $system->dbname_full();?></div>
    </div>

    <div>
        <div class="header"><label>Already registered with ID:</label></div>
        <div class="text ui-widget-content ui-corner-all" style="margin:4px"><?php echo $dbID;?></div>
    </div>

    <div>
        <div class="header" style="vertical-align:top"><label>Description:</label></div>
        <div class="text ui-widget-content ui-corner-all"
            style="width:450px;margin:4px" readonly="readonly">
            <?php echo $dbDescription;?>
         </div>
    </div>
    
    <div>
        <div class="header"><label><b>Please edit the collection metadata 
                describing this database:</b></label></div>
        <div class="text ui-widget-content ui-corner-all" style="margin:4px">
            <a href="<?php echo $edit_url;?>" target=_blank style='color:red;'>Click here to edit</a> (login as person who registered this database - 
                 note: use EMAIL ADDRESS as username)
        
        </div>
    </div>
                
    </fieldset>
 <?php
            } // existing registration
            
            if(!$isRegistrationInProgress && !$isAlreadyRegistred)
            { // New registration, display registration form
            
            $cuser = $system->getCurrentUser();
            
            $is_SpecialAdminUseOnly = strlen(@$passwordForServerFunctions)>6 && 
            @$cuser['ugr_Preferences']['userCompetencyLevel']==0 &&
            strpos(strtolower(HEURIST_SERVER_URL), strtolower(HEURIST_MAIN_SERVER))===0;  //we are on main reference server
            
?>

            <div id="registerDBForm" class="input-row" style="margin-top: 20px;">
                <form action="registerDB.php" method="POST" name="NewDBRegistration">

                   <input type="hidden" name="pwd" value="<?=$sysadmin_pwd?>">
                   <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
<fieldset style="padding-right:30px">

    <div>
        <div class="header" style="vertical-align:top;min-width:100px"><label>Registration URL:</label></div>
        <input readonly="readonly" style="backhround-color:lightgray;width: 520px;margin-bottom: 10px;"
            tabindex="-1"
            value="<?php echo HEURIST_SERVER_URL . '/heurist/' . "?db=" . HEURIST_DBNAME;?>"/>
    </div>
    <div>
        <div class="header" style="vertical-align:top;min-width:100px"><label>Database Description:</label></div>
        <textarea type="memo" maxlength="1000" cols="70" rows="3" name="dbDescription" id="dbDescription" class="text"
        style="border:1px solid;padding:2px" tabindex="0" autofocus
                                onkeyup="onKeyUpDbDescription( event )"><?php echo $dbDescription;?></textarea>

        <div class="heurist-helper1">
            Enter a short but informative description (minimum 40 characters) of this database (displayed in search list)
        </div>
                                
        <div style="padding-top:10px"  xstyle="display:inline-block;vertical-align:top;padding-left:4px">
                            <label id="cntChars" style="text-align:left"></label><br/>
                            <input id="btnSubmit" type="submit" name="submit" value="Register"
                                 style="padding:4px 6px;font-weight: bold;" onClick="hideRegistrationForm()" disabled="disabled" >
        </div>
        
        
        
    <div  style="margin-top: 15px; margin-bottom: 20px;">
        <div class="header"></div>
        <div>
        <br/>Note: After registering the database, you will be asked to log in to a Heurist database (<?php echo HEURIST_INDEX_DATABASE;?>).
        <br/>You should log into this database using your email address and the same login as your current database
        <br/>(or the first database you registered, if different). This will allow you to edit the collection metadata
        <br/>describing your database.
        </div>
<?php if($is_SpecialAdminUseOnly){?>
        <div style="border:1px solid gray;margin-top:14px;padding:14px">
            <label>SYSTEM ADMIN USE ONLY</label><br/><br/><label>ID </label>
            <input name="dbNewID" pattern="[0-9]" type="number" size="7" style="width:70px" value="<?php echo htmlspecialchars($dbNewID);?>"/>
            <label style="padding-left:50px">PASSWORD </label>
            <input name="dbNewID_pwd" type="password"/>
        </div>
<?php }?>
        
    </div>
    </div>
</fieldset>    

                </form>
            </div>



<?php
            } // new registration
?>

<!-- Explanation for the user THAT'S WRONG PLACE FOR IT!

            <div class="separator_row" style="margin:20px 0;"></div>
            <h3>Suggested workflow for new databases:</h3>

            <p>After creating a new database, please use the links: </p>
            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Database &gt; <u>Manage structure</u></div>
                <div class="detail">Modify the record types and fields describing them, and add new record types, fields and terms</div>
            </div>

            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Structure &gt; <u>Browse templates</u></div>
                <div class="detail">Browse and download record type definitions documented on the Heurist network web site. <br />
                    In addition to saving a great deal of time, the documentation helps you learn about the best ways of setting up Heurist structures.<br />
                    The record type definitions downloaded can later be modified to your requirements.</div>
            </div>
            <div class="detailRow">
                <div class="detailType" style="width: 180px !important;">Structure &gt; <u>Browse templates</u></div>
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
                <div class="detail">Register the database with the Heurist Reference Index.<br />
                    This will give your database a unique code and allow Heurist to check for new versions of
                    the software and database formats. <br/>It also allows other databases to import structural elements
                    (record types, field types and terms) but does NOT confer any form of access to data in this database</div>
            </div>
 -->
<?php

function registerDatabase() {
    
    
    global $dbowner, $system,
            $dbID, $dbName, $dbDescription, $ownerGrpID, $dbNewID;

                $heuristDBname = rawurlencode(HEURIST_DBNAME);
                //was HEURIST_BASE_URL - now always use hurist
                $serverURL = HEURIST_SERVER_URL . '/heurist/' . "?db=" . $heuristDBname;
                //remove http:// to avoid conversion path to localhost if masterindex on the same server
                //$serverURL = substr($serverURL,7); 
                
                $usrEmail = rawurlencode($dbowner['ugr_eMail']);
                $usrName = rawurlencode($dbowner['ugr_Name']);
                $usrFirstName = rawurlencode($dbowner['ugr_FirstName']);
                $usrLastName = rawurlencode($dbowner['ugr_LastName']);
                $usrPassword = rawurlencode($dbowner['ugr_Password']);
                
                $dbDescriptionEncoded = rawurlencode($dbDescription);
                
                $dbVersion = $system->get_system('sys_dbVersion').'.'
                                        .$system->get_system('sys_dbSubVersion').'.'
                                        .$system->get_system('sys_dbSubSubVersion');
                                        
                $params = array('db'=>HEURIST_INDEX_DATABASE,
                'dbReg'=>HEURIST_DBNAME,
                'dbVer'=>$dbVersion,
                'dbTitle'=>$dbDescription,
                'usrPassword'=>$dbowner['ugr_Password'],
                'usrName'=>$dbowner['ugr_Name'],
                'usrFirstName'=>$dbowner['ugr_FirstName'],
                'usrLastName'=>$dbowner['ugr_LastName'],
                'usrEmail'=>$dbowner['ugr_eMail'],
                'serverURL'=>$serverURL
                );
                if($dbNewID!=null && intval($dbNewID)>0){
                    $params['newid'] = $dbNewID;
                }

                if(strpos(HEURIST_INDEX_BASE_URL, HEURIST_SERVER_URL)===0){    
                
                    $data = DbUtils::databaseNextRegisterID($params);

                    $mysqli = $system->get_mysqli();
                    //restore connection back to currrent database
                    mysql__usedatabase($mysqli, HEURIST_DBNAME); 
                    
                }else{
                    
                    $reg_url =   HEURIST_INDEX_BASE_URL
                       .'admin/setup/dbproperties/getNextDBRegistrationID.php?'
                       .http_build_query($params);
                       
                    $data = loadRemoteURLContentWithRange($reg_url, null, true);
                    
                    if (!isset($data) || $data==null) {
                        
                        echo '<p class="ui-state-error">'
                            .'Unable to connect Heurist master index, possibly due to timeout or proxy setting<br />'
                            ."URL requested: <a href='$reg_url'>$reg_url</a></p>";
                        return false;
                    }
                    
                }                                   

                /*
                $reg_url =   HEURIST_INDEX_BASE_URL
                .'admin/setup/dbproperties/getNextDBRegistrationID.php' .
                "?db=".HEURIST_INDEX_DATABASE."&dbReg=" . $heuristDBname . "&dbVer=" . $dbVersion .
                "&dbTitle=" . $dbDescriptionEncoded . "&usrPassword=" . $usrPassword .
                "&usrName=" . $usrName . "&usrFirstName=" . $usrFirstName .
                "&usrLastName=" . $usrLastName . "&usrEmail=".$usrEmail
                ."&serverURL=" . rawurlencode($serverURL);
                $data = loadRemoteURLContentSpecial($reg_url); //without proxy
                */
                
                $mysqli = $system->get_mysqli();
                
                $dbID = intval($data);
                
                if ($dbID == 0) { // Unable to allocate a new database identifier
                    $decodedData = explode(',', $data);

                    if(count($decodedData)>1){
                        $msg = $decodedData[1];
                    }else{
                        $msg = "Problem allocating a database identifier from the Heurist master index, " .
                        "returned the following instead of a registration number:\n" . substr($data, 0, 25) .
                        " ... \nPlease ".CONTACT_HEURIST_TEAM." for advice";
                    }
                    echo '<p class="ui-state-error">'. $msg . "</p><br />";
                    return false;
                }
                else if($dbID == -1)
                { // old title update function, should no longer be called
                    $res = $mysqli->query(
                        "update sysIdentification set `sys_dbDescription`='".$mysqli->real_escape_string($dbDescription)."' where 1");
                    echo "<div class='input-row'><div class='header'>".
                    "Database description (updated):</div><div class='input-cell'>". $dbDescription."</div></div>";
                    
                    return true;
                } else
                { // We have got a new dbID, set the assigned dbID in sysIdentification
                

                    $res = $mysqli->query("update sysIdentification set `sys_dbRegisteredID`='$dbID', ".
                        "`sys_dbDescription`='".$mysqli->real_escape_string($dbDescription)."' where 1");
                        
                    if($res) {
                        
                        $edit_url = HEURIST_INDEX_BASE_URL.'?fmt=edit&recID='.$dbID.'&db='.HEURIST_INDEX_DATABASE;
 ?>                  
 <fieldset>
                        <div><div class='header'>Database:</div>
                        <div class='text'><?php echo HEURIST_DBNAME;?></div></div>
                        
                        <div><div class='header' style="vertical-align:top;">
                        Registration successful, database ID allocated is</div>
                        <div class='text' style="font-size:large;height:2em"><?php echo $dbID;?></div></div>
                        
                        <div><div class='header'>Basic description: </div>
                        <div class='text' style="width:450px;"><?php echo $dbDescription;?></div></div>
                        
                        <div><div class='header'>Collection metadata:</div>
                        <div class='text'><a href="<?php echo $edit_url;?>" target=_blank>Click here to edit</a>
                        (login - if asked - as yourself) </div></div>
 </fieldset>
<?php                        
                        // Update original DB ID and original db code for all existing record types, fields and terms
                        // which don't have them (meaning that they were defined within this database)
                        // Record types
                        $result = DbUtils::databaseRegister( $dbID );
                        
                        if (!$result) {
                            echo '<div class=wrap><div class="ui-state-error">Unable to set all values for originating DB information for '.HEURIST_DBNAME.' - one of the update queries failed. Please report a bug to Heurist team</div></div>';
                        }
                        ?>
                        <script> // automatically call Heurist Reference Index metadata edit form for this database
                            //need reload local defintitions and sysinfo on client side    
                            if(window.hWin){
                                window.hWin.HAPI4.EntityMgr.emptyEntityData(null); //reset all cached data for entities
                                window.hWin.HAPI4.SystemMgr.sys_info( function(){
                                    window.open("<?=$edit_url?>",'_blank');
                                }
                                );
                            }
                        </script>
                        <?php
                    } else {
                        $msg = '<div class=wrap><div class="ui-state-error"><span>Unable to write database identification record</span>'.
                        'this database might be incorrectly set up<br />'.
                        'Please '.CONTACT_HEURIST_TEAM.' for advice</div></div>';
                        echo $msg;
                    } // unable to write db identification record
                } // successful new DB ID
                return true;
            } // registerDatabase()

?>


        </div>
    </body>
</html>