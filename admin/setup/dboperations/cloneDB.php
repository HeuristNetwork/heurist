<?php
/**
* cloneDB.php: Copies an entire database verbatim
*                    Note that the cloning method was changed in 2014, using our own SQL dump function, to give more control
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*
  Cloning workflow:

    1. Create empty database with blankDBStructure.sql
    2. Empty sysIdentification, sysUsrGrpLinks, sysUGrps, defLanguages
    3. Copy ALL tables  
    4. reset reginfo
    5. Create indexes and procedures
    6. Copy db folder and update file path in recUploadedFiles

*/
set_time_limit(0);

define('MANAGER_REQUIRED', 1);   
define('PDIR','../../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');
require_once(dirname(__FILE__).'/../../../records/index/elasticSearch.php');
require_once('welcomeEmail.php');

//require_once(dirname(__FILE__).'/../../../hsapi/utilities/utils_db_load_script.php');

$user_id = $system->get_user_id(); //keep user id (need to copy current user into cloned db for template cloning)
$mysqli  = $system->get_mysqli();

$templateddb = @$_REQUEST['templatedb'];
$isCloneTemplate = ($templateddb!=null);
$sErrorMsg = null;
$sHasNewDefsWarning = false;

if($isCloneTemplate){ //template db must be registered with id less than 21

    $ERROR_REDIR = PDIR.'hclient/framecontent/infoPage.php';

    if(mysql__usedatabase($mysqli, $templateddb)!==true){
        $system->addError(HEURIST_ERROR, "Sorry, could not connect to the database $templateddb. Operation is possible when database to be cloned is on the same server");
        include $ERROR_REDIR;
        exit();
    }

    $dbRegID = $system->get_system('sys_dbRegisteredID', true);

    if(!($dbRegID>0 && $dbRegID<1000)){
        $system->addError(HEURIST_ERROR, "Sorry, the database $templateddb must be registered with an ID less than 1000, indicating a database curated or approved by the Heurist team, to allow cloning through this function. You may also clone any database that you can log into through the Advanced functions under Administration.");
        include $ERROR_REDIR;
        exit();
    }
}else{
    $templateddb = null;

    $dbRegID = $system->get_system('sys_dbRegisteredID', true);
    
    if(!($dbRegID>0)){
        //check for new definitions
        $rty = mysql__select_value($mysqli, 'SELECT count(*) FROM defRecTypes '
            ." WHERE (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL)");
        $dty = mysql__select_value($mysqli, 'SELECT count(*) FROM defDetailTypes '
            ." WHERE (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL)");
        $trm = mysql__select_value($mysqli, 'SELECT count(*) FROM defTerms '
            ." WHERE (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL)");
        
        if($rty>0 || $dty>0 || $trm>0){
            $s = array();
            if($rty>0) $s[] = $rty.' record types';
            if($dty>0) $s[] = $dty.' base fields';
            if($trm>0) $s[] = $trm.' vocabularies or terms';
            $sHasNewDefsWarning = implode(', ',$s);
        }
    }
}


// ---- PROCESS THE COPY FUNCTION (second pass) --------------------------------------------------------------------

//verify that name of database is unique
if(@$_REQUEST['mode']=='2'){

    if($sHasNewDefsWarning &&
    $system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForServerFunctions) ){

        $sErrorMsg = '<div class="ui-state-error">'
                    .$system->getError()['message'].'<br/></div>';
    }else{

        $targetdbname = $_REQUEST['targetdbname'];

        // Avoid illegal chars in db name
        $hasInvalid = preg_match('[\W]', $targetdbname);
        if ($hasInvalid) {
            $sErrorMsg = "<p><hr></p><p>&nbsp;</p><p>Requested database copy name: <b>$targetdbname</b></p>".
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
    if($sErrorMsg){
        $_REQUEST['mode'] = 0;
        $_REQUEST['targetdbname'] = null;
        unset($_REQUEST['targetdbname']);
    }
}
?>
<html>
    <head>
        <title>Clone Database</title>
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
                if (charCode && charCode > 31)
                {
                    var keyChar = String.fromCharCode(charCode);
                    if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){

                        event.cancelBubble = true;
                        event.returnValue = false;
                        event.preventDefault();
                        if (event.stopPropagation) event.stopPropagation();

                        /* does not work
                        var ele = event.target;
                        var evt = document.createEvent("KeyboardEvent");
                        evt.initKeyEvent("keypress",true, true, window, false, false,false, false, 0, 'A'.charCodeAt(0));
                        ele.dispatchEvent(evt);*/

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


        <div class="banner"><h2>Clone <?php print ($isCloneTemplate?'Template ':'')?>Database</h2></div>
        <div id="page-inner" style="overflow:auto">
            <div id="loading" style="display:none;height:100%" class="loading">
                <!-- <img alt="cloning ..." src="../../../common/images/mini-loading.gif" width="16" height="16" /> -->
                <div id="divProgress" style="font-weight:bold;width:100%;">&nbsp; Cloning of database may take a few minutes for large databases.</div>
            </div>
      
<?php    
if(@$_REQUEST['mode']=='2'){
    
    $targetdbname = $_REQUEST['targetdbname'];
    $nodata = (@$_REQUEST['nodata']==1);

    //$res = false;
    //sleep(15);
    
    $res = cloneDatabase($targetdbname, $nodata, $templateddb, $user_id);
    if(!$res){
        echo_flush ('<p style="padding-left:20px;"><h2 style="color:red">WARNING: Your database has not been cloned.</h2>'
        .'<p>Please run Verify &gt; Verify database integrity. If this does not find and fix the error, please send a bug report (Help &gt; Bug report) and we will investigate the problem.</p>');
    }

    print "</div></body></html>";
}else{
?>
    <div id="mainform">
        <form name='selectdb' action='cloneDB.php' method='post' onsubmit="{return onSubmit(event);}">

        <?php if(!$isCloneTemplate) { ?>

            <p>
                This function simply copies the current database <b> <?=HEURIST_DBNAME?> </b> to a new one with no changes. <br />
                The new database is identical to the old in all respects including users, access and attachments <br />
                (beware of making many copies of databases containing many large files, as all uploaded files are copied).<br />
                The target database is unregistered with the Heurist central index even if the source database is registered.
            </p>

            <?php
            if($sHasNewDefsWarning){
            ?>                        

                <p style="color:red">
                    Because this database contains new structural elements, you must register it before you can clone it.
                </p>
                <p>     
                    This database contains new definitions: <?php print $sHasNewDefsWarning; ?> which are local to the database.<br>
                    Before they can be cloned they must be attributed a unique global ID known as a Concept Code.<br>
                    This is done by registering the database. Please use Design > Setup > Register before cloning.<br><br>                 
                </p>         
                    <hr>
                <p style="font-size:smaller;padding:10px 0px">     
                    Sysadmin bypass (testing only: do not register cloned database): <input name="pwd" type="password"/>
                </p>         

        <?php        
            }
        }
        if($sErrorMsg){
            echo $sErrorMsg;
        }

        // ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

        ?>
        <div class="separator_row" style="margin:20px 0;"></div>
        <input name='mode' value='2' type='hidden'> <!-- calls the form to select mappings, step 2 -->
        <input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
        <?php
        if($isCloneTemplate){
            print '<input name="templatedb" value="'.$_REQUEST['templatedb'].'" type="hidden">';
        }
        ?>
        <p>The database will be created with the prefix <b><?=HEURIST_DB_PREFIX?></b>
            (all databases created by this installation of the software will have the same prefix).</p>
        <p>
            <label>No data (copy structure definitions only):&nbsp<input type='checkbox' name='nodata' value="1"/></label>
        </p>
        <h3 class="ui-heurist-title">Enter a name for the cloned database:</h3>
        <div style="margin-left: 40px;">
            <input type='text' name='targetdbname' id='targetdbname' size="40" onkeypress="{onKeyPress(event)}"/>
            <input type='submit' id='submitBtn' 
                value='Clone "<?=($isCloneTemplate)?$_REQUEST['templatedb']:HEURIST_DBNAME?>"'
                class="ui-button-action"/>
        </div>

    </div>
    </form>
</div>
        </body>
    </html>
    <?php
    exit;
}
// ---- COPY FUNCTION -----------------------------------------------------------------

//
// 1. create empty database
// 2. clean default values from some tables
// 3. clean content of all tables
// 4. add contrainsts, procedure and triggers
// 5. remove registration info and assign originID for definitions
//
function cloneDatabase($targetdbname, $nodata=false, $templateddb, $user_id) {
    global $errorScriptExecution, $mysqli, $system;
    
    $isCloneTemplate = ($templateddb!=null);
    
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
    
    // Connect to new database and  Remove initial values from empty database
    mysql__usedatabase($mysqli, $targetdbname_full);
    $mysqli->query('delete from sysIdentification where 1');
    $mysqli->query('delete from defLanguages where 1');
    
    if($isCloneTemplate){
        $source_database = $templateddb;
        //copy current user from current HEURIST_DBNAME_FULL to cloned database as user#2
        $mysqli->query('update sysUGrps u1, '.HEURIST_DBNAME_FULL.'.sysUGrps u2 SET '
.'u1.ugr_Type=u2.ugr_Type ,u1.ugr_Name=u2.ugr_Name ,u1.ugr_LongName=u2.ugr_LongName ,u1.ugr_Description=u2.ugr_Description '
.',u1.ugr_Password=u2.ugr_Password ,u1.ugr_eMail=u2.ugr_eMail, u1.ugr_FirstName=u2.ugr_FirstName ,u1.ugr_LastName=u2.ugr_LastName '
.',u1.ugr_Department=u2.ugr_Department ,u1.ugr_Organisation=u2.ugr_Organisation ,u1.ugr_City=u2.ugr_City '
.',u1.ugr_State=u2.ugr_State ,u1.ugr_Postcode=u2.ugr_Postcode ,u1.ugr_Interests=u2.ugr_Interests ,u1.ugr_Enabled=u2.ugr_Enabled '
.',u1.ugr_MinHyperlinkWords=u2.ugr_MinHyperlinkWords '
.',u1.ugr_IsModelUser=u2.ugr_IsModelUser ,u1.ugr_IncomingEmailAddresses=u2.ugr_IncomingEmailAddresses '
.',u1.ugr_TargetEmailAddresses=u2.ugr_TargetEmailAddresses ,u1.ugr_URLs=u2.ugr_URLs,u1.ugr_FlagJT=u2.ugr_FlagJT '
        .' where u1.ugr_ID=2 AND u2.ugr_ID='.$user_id);
        
    }else{
        $source_database = HEURIST_DBNAME_FULL;
        $mysqli->query('delete from sysUsrGrpLinks where 1');
        $mysqli->query('delete from sysUGrps where ugr_ID>=0');
    }

    list($source_database_full, $source_database) = mysql__get_names( $source_database );
    

    echo_flush ("<p><b>Copying data</b></p>");
    // db_clone function in /common/php/db_utils.php does all the work
    if( DbUtils::databaseClone($source_database_full, $targetdbname_full, true, $nodata, $isCloneTemplate) ){
        echo_flush ('<p style="padding-left:20px">Data copied OK</p>');
    }else{
        DbUtils::databaseDrop( false, $targetdbname_full, false);
        
        return false;
    }
    
    //cleanup target database to avoid issues with addition of constraints

    //1. cleanup missed trm_InverseTermId
    $mysqli->query('update defTerms t1 left join defTerms t2 on t1.trm_InverseTermId=t2.trm_ID
        set t1.trm_InverseTermId=null
    where t1.trm_ID>0 and t2.trm_ID is NULL');
    
    //3. remove missed rl_SourceID and rl_TargetID
    $mysqli->query('delete FROM recLinks
        where rl_SourceID is not null
    and rl_SourceID not in (select rec_ID from Records)');

    $mysqli->query('delete FROM recLinks
        where rl_TargetID is not null
    and rl_TargetID not in (select rec_ID from Records)');
    
    //4. cleanup orphaned details
    $mysqli->query('delete FROM recDetails
        where dtl_RecID is not null
    and dtl_RecID not in (select rec_ID from Records)');
    
    //5. cleanup missed references to uploaded files
    $mysqli->query('delete FROM recDetails
        where dtl_UploadedFileID is not null
    and dtl_UploadedFileID not in (select ulf_ID from recUploadedFiles)');

    //6. cleanup missed rec tags links
    $mysqli->query('delete FROM usrRecTagLinks where rtl_TagID not in (select tag_ID from usrTags)');
    $mysqli->query('delete FROM usrRecTagLinks where rtl_RecID not in (select rec_ID from Records)');

    //7. cleanup orphaned bookmarks
    $mysqli->query('delete FROM usrBookmarks where bkm_RecID not in (select rec_ID from Records)');

/*    
    echo_flush ("<p>DEBUG. Db created without indicies and triggers</p>");
    return true;
*/    
    $sHighLoadWarning = "<p><h4>Note: </h4>Failure to clone a database may result from high server load. Please try again, and if the problem continues ".CONTACT_HEURIST_TEAM."</p>";
    
    // 4. add contrainsts, procedure and triggers
    echo_flush ("<p><b>Addition of Referential Constraints</b></p>");
    
    if(db_script($targetdbname_full, HEURIST_DIR."admin/setup/dbcreate/addReferentialConstraints.sql")){
        echo_flush ('<p style="padding-left:20px">Referential constraints added OK</p>');
    }else{
        DbUtils::databaseDrop( false, $targetdbname_full, false);
        print '<p><h4>Note: </h4>Cloning failed due to an SQL constraints problem (internal database inconsistency). Please '
                .CONTACT_HEURIST_TEAM
                .' and request a fix for this problem - it should be cleaned up even if you don\'t need to clone the database</p>'
                .$errorScriptExecution;
        return false;
    }

    echo_flush ("<p><b>Addition of Procedures and Triggers</b></p>");
    if(db_script($targetdbname_full, HEURIST_DIR."admin/setup/dbcreate/addProceduresTriggers.sql")){
        echo_flush ('<p style="padding-left:20px">Procedures and triggers added OK</p>');
    }else{
        DbUtils::databaseDrop( false, $targetdbname_full, false);
        print $sHighLoadWarning.$errorScriptExecution;
        return false;
    }    
    
    // 5. remove registration info and assign originID for definitions
    $sourceRegID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');

    //print "<p>".$sourceRegID."</p>";
    // RESET register db ID and zotero credentials
    $query1 = "update sysIdentification set sys_dbRegisteredID=0, sys_hmlOutputDirectory=null, sys_htmlOutputDirectory=null, sys_SyncDefsWithDB=null, sys_MediaFolders='uploaded_files', sys_eMailImapProtocol='', sys_eMailImapUsername='', sys_dbRights='', sys_NewRecOwnerGrpID=0 where 1";
    $res1 = $mysqli->query($query1);
    if ($mysqli->error)  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to reset sys_dbRegisteredID in sysIdentification table. (".$mysqli->error.
        ")<br> Please reset the registration ID manually</b></p>";
    }
    //assign origin ID    
    DbUtils::databaseRegister($sourceRegID);

    // Index new database for Elasticsearch
    //TODO: Needs error report, trap error and warn or abort clone
    ElasticSearch::buildAllIndices($targetdbname_full); // ElasticSearch uses full database name including prefix

    // Copy the images and the icons directories
    //TODO: Needs error report, trap error and warn or abort clone
    if($nodata){
        DbUtils::databaseCreateFolders($targetdbname);

// *** DO NOT FORGET TO ADD NEW DIRECTORIES TO CLONING FUNCTION **         

        folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_database."/smarty-templates", 
                    HEURIST_FILESTORE_ROOT.$targetdbname."/smarty-templates" );
        folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_database."/xsl-templates", 
                    HEURIST_FILESTORE_ROOT.$targetdbname."/xsl-templates" );
        folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_database."/rectype-icons", 
                    HEURIST_FILESTORE_ROOT.$targetdbname."/rectype-icons" );
        folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_database."/entity", 
                    HEURIST_FILESTORE_ROOT.$targetdbname."/entity" );
        
    }else{
        folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_database, HEURIST_FILESTORE_ROOT.$targetdbname );    
    }
    

    // Update file path in target database  with absolute paths
    $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_FILESTORE_ROOT.$targetdbname.
    "/' where ulf_FilePath='".HEURIST_FILESTORE_ROOT.$source_database."/' and ulf_ID>0";
    $res1 = $mysqli->query($query1);
    if ($mysqli->error)  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to set database files path to new path</b>".
        "<br>Query was:".$query1.
        "<br>Please get your system administrator to fix this problem BEFORE editing the database (your edits will affect the original database)</p>";
    }

    // Success!
    echo "<hr><p>&nbsp;</p><h2>New database '$targetdbname' created successfully</h2>";
    print "<p>Please access your new database through this link: <a href='".HEURIST_BASE_URL."?db=".$targetdbname.
    "' title='' target=\"_new\"><strong>".$targetdbname."</strong></a></p>";
    
    //SEND EMAIL ABOUT CREATING NEW DB
    $user_record = mysql__select_row_assoc($mysqli, 'select ugr_Name, ugr_FirstName, ugr_LastName,'
         .'ugr_Organisation,ugr_eMail,ugr_Interests '
         .' FROM sysUGrps WHERE ugr_ID='.($isCloneTemplate?'2':$user_id));

    if($user_record){
            sendEmail_NewDatabase($user_record, $targetdbname_full, 
                ' from  '.($isCloneTemplate?'template ':'').$source_database_full);
    }
    
    return true;
} // straightCopyNewDatabase
?>