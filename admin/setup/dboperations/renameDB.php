<?php
/**
* renameDB.php: performs actions necessary to rename a database (create clone, then delete original)
*  
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('MANAGER_REQUIRED', 1); 
define('PDIR','../../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');

$user_id = $system->get_user_id();
$mysqli  = $system->get_mysqli();

$sErrorMsg = null;

$regID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');
if($regID > 0 && $user_id != 2){
    print '<h4 style="margin-inline-start: 10px;margin-block-start: 20px;">Renaming registered databases can only be performed by the database owner</h4>';
    exit();
}

if($_REQUEST['mode'] == 2){ // verify the new name is unique

	$targetdbname = $_REQUEST['targetdbname'];

    if(strlen($targetdbname)>64){ // validate length
        $sErrorMsg = 'Database name <b>'.$targetdbname.'</b> is too long. Max 64 characters allowed';
    }else{
        // Avoid illegal chars in db name
        $hasInvalid = preg_match('[\W]', $targetdbname);
        if ($hasInvalid) {
            $sErrorMsg = "<p><hr></p><p>&nbsp;</p><p>Requested database rename: <b>$targetdbname</b></p>".
            "<p>Sorry, only letters, numbers and underscores (_) are allowed in the database name</p>";
        } // rejecting illegal characters in db name
        else{
            list($targetdbname, $dbname) = mysql__get_names( $targetdbname );

            $dblist = mysql__select_list2($mysqli, 'show databases');
            if (array_search(strtolower($targetdbname), array_map('strtolower', $dblist)) !== false ){
                $sErrorMsg = "<div class='ui-state-error'>Warning: database '".$targetdbname
                ."' already exists. Please choose a different name<br/></div>";
            }else{
                ob_start();
            }
        }
    }

    if($sErrorMsg !== null){
        $_REQUEST['mode'] = 0;
        $_REQUEST['targetdbname'] = null;
        unset($_REQUEST['targetdbname']);
    }
}
?>
<html>
    <head>
        <title>Rename Database</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <!-- jQuery UI -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../../hclient/framecontent/initPageCss.php'; ?>

        <!-- Heurist JS -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>        

        <style>
            ul {color:#CCC;}
            li {line-height: 20px; list-style:outside square; font-size:9px;}
            li ul {color:#CFE8EF; font-size:9px}
            li span {font-size:11px; color:#000;}
            h2,h3{margin:0}
        </style>
    </head>
    <body class="popup ui-heurist-admin">
    	<script type="text/javascript">
        
            $(document).ready(function(){
                $('#submitBtn').button();
            })
            //
            // allow only alphanum chars for database name    @todo replace with utils_ui.preventNonAlphaNumeric
            //
            function onKeyPress(event){

                event = event || window.event;
                var charCode = typeof event.which == "number" ? event.which : event.keyCode;
                if (charCode && charCode > 31){

                    var keyChar = String.fromCharCode(charCode);
                    if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){

                        event.cancelBubble = true;
                        event.returnValue = false;
                        event.preventDefault();
                        if (event.stopPropagation) event.stopPropagation();

                        return false;
                    }
                }
                return true;
            }

            function onSubmit(event){
                
                var ele = document.getElementById("targetdbname");
                
                if(ele.value.trim()==''){
                    window.hWin.HEURIST4.msg.showMsgFlash('Define name of new database');
                    return false;
                }else{
                    ele = document.getElementById("submitBtn");
                    ele.disabled = 'disabled';

                    onStartProgress();    
                    return true;
                    //document.forms[0].submit();
                }
            }
            
            function onStartProgress(){
                ele = document.getElementById("loading");
                ele.style.display = "block";
                ele = document.getElementById("mainform");
                if(ele) ele.style.display = "none";

                showProgress();
            }
                
            function showProgress(){

                var ele = document.getElementById("divProgress");
                if(ele){
                    ele.innerHTML = ele.innerHTML + ". ";
                    setTimeout(showProgress, 500);
                }
            }

        </script>


        <div class="banner"><h2>Rename Database</h2></div>
        <div id="page-inner" style="overflow:auto">
            <div id="loading" style="display:none;height:100%" class="loading">
                <!-- <img alt="cloning ..." src="<?php echo HEURIST_BASE_URL.'hclient/assets/mini-loading.gif'?>" width="16" height="16" /> -->
                <div id="divProgress" style="font-weight:bold;width:100%;">&nbsp; Renaming of database may take a few minutes for large databases.</div>
            </div>
<?php

if(@$_REQUEST['mode']=='2'){
    
    $targetdbname = $_REQUEST['targetdbname'];
    $nodata = (@$_REQUEST['nodata']==1);
    
    $res = perform_rename($targetdbname);
    if(!$res){
        echo_flush ('<p style="padding-left:20px;"><h2 style="color:red">Your database has not been renamed.</h2>'
        .'<p>Please run Verify &gt; Verify database integrity. If this does not find and fix the error, please send a bug report (Help &gt; Bug report) and we will investigate the problem.</p>');
    }

    print "</div></body></html>";
}else{
?>
            <div id="mainform">
                <div>
                    This will perform the clone and delete functions back to back in order to rename the current database, so please ensure all edits/changes have been saved before proceeding.<br>
                    After successful completion you will be required to login into the newly cloned database.
                </div>
                <form name='selectdb' action='renameDB.php' method='post' onsubmit="{return onSubmit(event);}">

                <?php
                if($sErrorMsg){
                    echo $sErrorMsg;
                }

                // ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

                ?>
                    <div class="separator_row" style="margin:20px 0;"></div>
                    <input name='mode' value='2' type='hidden'> <!-- calls the form to select mappings, step 2 -->
                    <input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>

                    <p>The database will be created with the prefix <b><?=HEURIST_DB_PREFIX?></b>
                        (this is for internal reference only, it need not concern you).</p>

                    <h3 class="ui-heurist-title">Enter a new name for your database:</h3>
                    <div style="margin-left: 40px;">
                        <input type='text' name='targetdbname' id='targetdbname' size="40" maxlength="64" onkeypress="{onKeyPress(event)}"/>
                        <input type='submit' id='submitBtn' 
                            value='Rename "<?= HEURIST_DBNAME ?>"'
                            class="ui-button-action"/>
                    </div>

                </form>
            </div>

        </div>
    </body>
</html>
<?php
	exit();
}

function perform_rename($new_name){

	global $system, $mysqli;
    $org_name = HEURIST_DBNAME;
    list($new_dbname_full, $new_db_name) = mysql__get_names($new_name);
    $new_url = HEURIST_BASE_URL."?db=".$new_db_name;

    if(!$system->is_admin()){
        print '<h4 style="margin-inline-start: 10px;margin-block-start: 20px;">Only database administrator may use this function!</h4>';
        return false;
    }

	// clone db
    if(!perform_clone($mysqli, $new_db_name, $org_name)){
        return false;
    }

    print '<p><b>Cloning Completed</b></p><br>';

    // update details for registered database
    $regID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');
    if($regID > 0){

        print '<p><b>Updating registered database record on Heurist Master Index</b></p><br>';
        // Update registered db record in Index database
        $res = updateRegDetails($mysqli, $regID, $new_db_name, $new_dbname_full);

        if(!$res){
            return false;
        }

        print '<p><b>Completed record update</b></p><br>';
    }

    print '<p><b>Archiving current database</b></p><br>';
	// delete current db - create archive
    if(!DbUtils::databaseDrop(true, $org_name, true)){
        DbUtils::databaseDrop(false, $new_dbname_full, false);

        if($regID > 0){
            $res = updateRegDetails($mysqli, $regID, $new_db_name, $new_dbname_full);

            if(!$res){
                //print '<br><br><p><b>Please contact the Heurist team about this error</b></p>';
                return false;
            }
        }

        return false;
    }

    // transfer user to new db
    print '<script>window.hWin.HEURIST4.msg.showMsgDlg("Your renamed database is accessible from <a href=\''. $new_url .'\'>here</a>", null, '
            . '"Databse renamed", {close: function(){window.hWin.document.location = \''. $new_url .'\';}});</script>';

    return true;
}

function updateRegDetails($mysqli, $regID, $new_db_name, $new_dbname_full){

    //$dbowner = user_getDbOwner($mysqli);
    $serverURL = HEURIST_SERVER_URL . '/heurist/' . "?db=" . $new_db_name;

    $params = array(
        'db'=>HEURIST_INDEX_DATABASE,
        'dbID'=>$regID,
        //'org_dbReg'=>$org_name,
        'dbReg'=>$new_db_name,
        'usrPassword'=>$dbowner['ugr_Password'],
        'usrEmail'=>$dbowner['ugr_eMail'],
        'serverURL'=>$serverURL
    );

    $data = 'Unknown error';
    if(strpos(HEURIST_INDEX_BASE_URL, HEURIST_SERVER_URL)===0){
        $data = DbUtils::updateRegisteredDatabase($params);
    }else{
        $reg_url =   HEURIST_INDEX_BASE_URL
            .'admin/setup/dbproperties/getNextDBRegistrationID.php?'
            .http_build_query($params);

        $data = loadRemoteURLContentWithRange($reg_url, null, true);

        if (!isset($data) || $data==null) {
            global $glb_curl_error;
            $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

            echo '<p class="ui-state-error">'
                .'Unable to connect Heurist master index, possibly due to timeout or proxy setting<br><br>'
                . $error_code . '<br>'
                ."URL requested: $reg_url</p><br>";
            return false;
        }
    }

    if($data != $regID){
        DbUtils::databaseDrop(false, $new_dbname_full, false);
        echo $data;
        return false;    
    }

    return true;
}

function perform_clone($mysqli, $targetdbname, $sourcedbname){

    list($targetdbname_full, $targetdbname) = mysql__get_names( $targetdbname );

    //create new empty database and structure
    echo_flush ('<p>Creating Database Structure (tables)</p>'
    .'<div id="wait_p" class="loading" style="width:95%;height:150px">'
    .'<i>Please wait for confirmation message (may take a couple of minutes for large databases)</i></div>');
    
    if(!DbUtils::databaseCreate($targetdbname_full, 1)){
        $err = $system->getError();
        echo_flush ('<script>document.getElementById("wait_p").style.display="none"</script>'
        .'<div style="padding:10px 20px;font-weight:normal" class="ui-state-error">'
            .$err['message']
            .(@$err['sysmsg']?('<br><br>System message: '.$err['sysmsg']):'')
        .'</div>');
        
        return false;
    }else{
        echo_flush ('<script>document.getElementById("wait_p").style.display="none"</script><p style="padding-left:0px">Structure created OK</p>');
    }

    //current database
    list($source_database_full, $source_database) = mysql__get_names( $sourcedbname );

    echo_flush ("<p><b>Copying data</b></p>");
    // db_clone function in /common/php/db_utils.php does all the work
    if( DbUtils::databaseClone($source_database_full, $targetdbname_full, true, false, null) ){
        echo_flush ('<p style="padding-left:20px">Data copied OK</p>');
    }else{
        DbUtils::databaseDrop( false, $targetdbname_full, false);
        return false;
    }
    
    // 4. add contrainsts, procedure and triggers
    echo_flush ("<p><b>Addition of Referential Constraints</b></p>");
    
    if(DbUtils::databaseCreateConstraintsAndTriggers($targetdbname_full)){
        echo_flush ('<p style="padding-left:20px">Referential constraints added OK</p>');
        echo_flush ('<p style="padding-left:20px">Procedures and triggers added OK</p>');
    }else{
        print '<p><h4>Note: </h4>Cloning failed due to an SQL constraints problem (internal database inconsistency). Please '
                .CONTACT_HEURIST_TEAM
                .' and request a fix for this problem - it should be cleaned up even if you don\'t need to clone the database</p>'
                .$errorScriptExecution;

        DbUtils::databaseDrop( false, $targetdbname_full, false);
        return false;
    }

    // Copy the images and the icons directories
    $res = folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_database, HEURIST_FILESTORE_ROOT.$targetdbname );    

    // Update file path in target database  with absolute paths
    $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_FILESTORE_ROOT.$targetdbname.
    "/' where ulf_FilePath='".HEURIST_FILESTORE_ROOT.$source_database."/' and ulf_ID>0";
    $res1 = $mysqli->query($query1);
    if ($mysqli->error)  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to set database files path to new path</b>".
        "<br>Query was:".$query1.
        "<br>Please get your system administrator to fix this problem BEFORE editing the database (your edits will affect the original database)</p>";

        DbUtils::databaseDrop( false, $targetdbname_full, false);
        return false;
    }

    // Successful
    return true;
}