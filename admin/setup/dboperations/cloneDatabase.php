<?php
//MOVE TO H4

/**
* cloneDatabase.php: Copies an entire database verbatim
*                    Note that the cloning method was changed in 2014, using our own SQL dump function, to give more control
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
    2. Empty sysIdentification, sysTableLastUpdated, sysUsrGrpLinks, sysUGrps, defLanguages
    3. Copy ALL tables  
    4. reset reginfo
    5. Create indexes and procedures
    6. Copy db folder and update file path in recUploadedFiles

*/

require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../records/index/elasticSearchFunctions.php');
require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');
require_once(dirname(__FILE__).'/../../../hserver/dbaccess/utils_db_load_script.php');

if(isForAdminOnly("to clone a database")){ //first 20 databases are templates
    return;
}

$user_id = get_user_id(); //keep user id (need to copy current user into cloned db for template cloning)

$templateddb = @$_REQUEST['templatedb'];
$isCloneTemplate = ($templateddb!=null);

if($isCloneTemplate){ //template db must be registered with id less than 21

    if(strpos($templateddb,'hdb_')!==0){
        $templateddb = HEURIST_DB_PREFIX .$templateddb;
    }
    mysql_connection_select($templateddb);
    if(mysql_error()) {
        die("<h2>Error</h2>Sorry, could not connect to the database $templateddb (mysql_connection_overwrite error)");
    }
    
    $dbRegID = 'SELECT sys_dbRegisteredID FROM sysIdentification';
    $res = mysql_query('select sys_dbRegisteredID from sysIdentification');
    if (!$res) {
        die("<h2>Error</h2>Unable to read database description from sysIdentification table. ".
            "May indicate corrupted database. ".$talkToSysAdmin." MySQL error: " . mysql_error());
    }
    $res = mysql_fetch_row($res);
    $dbRegID = $res[0];
    
    if(!($dbRegID>0 && $dbRegID<21)){
        die("<h2>Error</h2>The database $templateddb is not a template. It must be registered and has ID within specific range");
    }
}else{
    $templateddb = null;
    mysql_connection_overwrite(DATABASE);
    if(mysql_error()) {
        die("<h2>Error</h2>Sorry, could not connect to the database ".DATABASE." (mysql_connection_overwrite error)");
    }
}

?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Clone Database</title>

        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">

        <style>
            ul {color:#CCC;}
            li {line-height: 20px; list-style:outside square; font-size:9px;}
            li ul {color:#CFE8EF; font-size:9px}
            li span {font-size:11px; color:#000;}
        </style>
    </head>
    <body class="popup">

        <script type="text/javascript">
            //
            // allow only alphanum chars for database name
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
                event.target.disabled = 'disabled';

                var ele = document.getElementById("loading");
                ele.style.display = "block";
                ele = document.getElementById("mainform");
                ele.style.display = "none";

                showProgress();
                document.forms[0].submit();
            }

            function showProgress(){

                var ele = document.getElementById("divProgress");
                if(ele){
                    ele.innerHTML = ele.innerHTML + ".";
                    setTimeout(showProgress, 500);
                }
            }

        </script>


        <div class="banner"><h2>Clone <?php print ($isCloneTemplate?'Template ':'')?>Database</h2></div>

        <script type="text/javascript" src="../../../common/js/utilsLoad.js"></script>
        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>
        <script src="../../../common/php/loadCommonInfo.php"></script>
        <div id="page-inner" style="overflow:auto">

        
            <div id="loading" style="display:none">
                <img alt="cloning ..." src="../../../common/images/mini-loading.gif" width="16" height="16" />
                <strong><span id="divProgress">&nbsp; Cloning of database may take a few minutes for large databases </span></strong>
            </div>
            <div id="mainform">

                <?php if(!$isCloneTemplate) { ?>

                <p>
                    This function simply copies the current database <b> <?=HEURIST_DBNAME?> </b> to a new one with no changes. <br />
                    The new database is identical to the old in all respects including users, access and attachments <br />
                    (beware of making many copies of databases containing many large files, as all uploaded files are copied).<br />
                    The target database is unregistered with the Heurist central index even if the source database is registered.
                </p>

                <?php
                
                }
                
                //verify that name of database is unique
                if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){
                    $targetdbname = $_REQUEST['targetdbname'];

                    // Avoid illegal chars in db name
                    $hasInvalid = isInValid($targetdbname);
                    if ($hasInvalid) {
                        echo ("<p><hr><p>&nbsp;<p>Requested database copy name: <b>$targetdbname</b>".
                            "<p>Sorry, only letters, numbers and underscores (_) are allowed in the database name");

                            $_REQUEST['mode'] = 0;
                            $_REQUEST['targetdbname'] = null;
                            unset($_REQUEST['targetdbname']);
                    } // rejecting illegal characters in db name
                    else{

                        $list = mysql__getdatabases();
                        $list = array_map("arraytolower", $list);
                        if(in_array(strtolower($targetdbname), $list)){
                            echo ("<p class='error'>Warning: database '".$targetdbname."' already exists. Please choose a different name<br/></p>");
                            $_REQUEST['mode'] = 0;
                            $_REQUEST['targetdbname'] = null;
                            unset($_REQUEST['targetdbname']);
                        }
                    }
                }
                

                // ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

                if(@$_REQUEST['mode']!='2' || !@$_REQUEST['targetdbname']){
                    ?>
                    <div class="separator_row" style="margin:20px 0;"></div>
                    <form name='selectdb' action='cloneDatabase.php' method='get'>
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
                        <h3>Enter a name for the cloned database:</h3>
                        <div style="margin-left: 40px;">
                            <input type='text' name='targetdbname' size="40" onkeypress="{onKeyPress(event)}"/>
                            <input type='button' value='Clone "<?=($isCloneTemplate)?$_REQUEST['templatedb']:HEURIST_DBNAME?>"'
                                onclick='onSubmit(event)'/>
                        </div>

                    </form>
                </div>
            </div>
        </body>
    </html>
    <?php
    exit;
}

// ---- PROCESS THE COPY FUNCTION (second pass) --------------------------------------------------------------------


function isInValid($str) {
    return preg_match('[\W]', $str);
}
function arraytolower($item)
{
    return strtolower($item);
}

if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){

    $targetdbname = $_REQUEST['targetdbname'];
    $nodata = (@$_REQUEST['nodata']==1);
    $res = cloneDatabase($targetdbname, $nodata, $templateddb);
    
    if(!$res){
        echo_flush ('<p style="padding-left:20px;"><h2 style="color:red">WARNING: Your database has not been cloned.</h2>'
        .'Please contact your system administrator or the Heurist developers (support at HeuristNetwork dot org) for assistance with cloning of your database.');
    }
        


    print "</div></body></html>";
}

// ---- COPY FUNCTION -----------------------------------------------------------------

//
// 1. create empty database
// 2. clean default values from some tables
// 3. clean content of all tables
// 4. add contrainsts, procedure and triggers
// 5. remove registration info and assign originID for definitions
//
function cloneDatabase($targetdbname, $nodata=false, $templateddb) {
    set_time_limit(0);

    $isCloneTemplate = ($templateddb!=null);
    
    $newname = HEURIST_DB_PREFIX.$targetdbname;

    //create new empty database
    if(!db_create($newname)){
        return false;
    }

    echo_flush ("<p>Create Database Structure (tables)</p>");
    if(db_script($newname, HEURIST_DIR."admin/setup/dbcreate/blankDBStructure.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        return false;
    }
    
    // Remove initial values from empty database
    mysql_connection_insert($newname);
    mysql_query('delete from sysIdentification where 1');
    mysql_query('delete from sysTableLastUpdated where 1');
    mysql_query('delete from defLanguages where 1');
    
    if($isCloneTemplate){
        $source_database = $templateddb;
        //copy current user from DATABASE to cloned database as user#2
        mysql_query('update sysUGrps u1, '.$source_database.'.sysUGrps u2 SET '
.'u1.ugr_Type=u2.ugr_Type ,u1.ugr_Name=u2.ugr_Name ,u1.ugr_LongName=u2.ugr_LongName ,u1.ugr_Description=u2.ugr_Description '
.',u1.ugr_Password=u2.ugr_Password ,u1.ugr_eMail=u2.ugr_eMail, u1.ugr_FirstName=u2.ugr_FirstName ,u1.ugr_LastName=u2.ugr_LastName '
.',u1.ugr_Department=u2.ugr_Department ,u1.ugr_Organisation=u2.ugr_Organisation ,u1.ugr_City=u2.ugr_City '
.',u1.ugr_State=u2.ugr_State ,u1.ugr_Postcode=u2.ugr_Postcode ,u1.ugr_Interests=u2.ugr_Interests ,u1.ugr_Enabled=u2.ugr_Enabled '
.',u1.ugr_MinHyperlinkWords=u2.ugr_MinHyperlinkWords '
.',u1.ugr_IsModelUser=u2.ugr_IsModelUser ,u1.ugr_IncomingEmailAddresses=u2.ugr_IncomingEmailAddresses '
.',u1.ugr_TargetEmailAddresses=u2.ugr_TargetEmailAddresses ,u1.ugr_URLs=u2.ugr_URLs,u1.ugr_FlagJT=u2.ugr_FlagJT '
        .' where u1.ugr_ID=2 AND u2.ugr_ID='.get_user_id());
        
    }else{
        $source_database = DATABASE;
        mysql_query('delete from sysUsrGrpLinks where 1');
        mysql_query('delete from sysUGrps where ugr_ID>=0');
    }

    

    echo_flush ("<p>Copy data</p>");
    // db_clone function in /common/php/db_utils.php does all the work
    if( db_clone($source_database, $newname, true, $nodata, $isCloneTemplate) ){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        
        return false;
    }
    
    //cleanup target database to avoid issues with addition of constraints
    mysql_connection_insert($newname);

    //1. cleanup missed trm_InverseTermId
    mysql_query('update defTerms t1 left join defTerms t2 on t1.trm_InverseTermId=t2.trm_ID
        set t1.trm_InverseTermId=null
    where t1.trm_ID>0 and t2.trm_ID is NULL');
    
    //2. remove missed recent records
    mysql_query('delete FROM usrRecentRecords
        where rre_RecID is not null
    and rre_RecID not in (select rec_ID from Records)');
    
    //3. remove missed rrc_SourceRecID and rrc_TargetRecID
    mysql_query('delete FROM recRelationshipsCache
        where rrc_SourceRecID is not null
    and rrc_SourceRecID not in (select rec_ID from Records)');

    mysql_query('delete FROM recRelationshipsCache
        where rrc_TargetRecID is not null
    and rrc_TargetRecID not in (select rec_ID from Records)');
    
    //4. cleanup orphaned details
    mysql_query('delete FROM recDetails
        where dtl_RecID is not null
    and dtl_RecID not in (select rec_ID from Records)');
    
    //5. cleanup missed references to uploaded files
    mysql_query('delete FROM recDetails
        where dtl_UploadedFileID is not null
    and dtl_UploadedFileID not in (select ulf_ID from recUploadedFiles)');

    //6. cleanup missed rec tags links
    mysql_query('delete FROM usrRecTagLinks where rtl_TagID not in (select tag_ID from usrTags)');
    mysql_query('delete FROM usrRecTagLinks where rtl_RecID not in (select rec_ID from Records)');

    //7. cleanup orphaned bookmarks
    mysql_query('delete FROM usrBookmarks where bkm_RecID not in (select rec_ID from Records)');

    
    $sHighLoadWarning = "<p><h4>Note: </h4>Failure to clone a database may result from high server load. Please try again, and if the problem continues contact the Heurist developers at info heuristnetwork dot org</p>";
    
    // 4. add contrainsts, procedure and triggers
    echo_flush ("<p>Addition of Referential Constraints</p>");
    if(db_script($newname, dirname(__FILE__)."/../dbcreate/addReferentialConstraints.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        print $sHighLoadWarning;
        return false;
    }

    echo_flush ("<p>Addition of Procedures and Triggers</p>");
    if(db_script($newname, dirname(__FILE__)."/../dbcreate/addProceduresTriggers.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        print $sHighLoadWarning;
        return false;
    }

    // 5. remove registration info and assign originID for definitions
    $sourceRegID = 0;
    $res = mysql_query('select sys_dbRegisteredID from sysIdentification where 1');
    if($res){
        $row = mysql_fetch_row($res);
        if($row){
            $sourceRegID = $row[0];
        }
    }
    //print "<p>".$sourceRegID."</p>";
    // RESET register db ID and zotero credentials
    mysql_connection_insert($newname);
    $query1 = "update sysIdentification set sys_dbRegisteredID=0, sys_hmlOutputDirectory=null, sys_htmlOutputDirectory=null, sys_SyncDefsWithDB=null, sys_MediaFolders=null, sys_eMailImapProtocol=null, sys_eMailImapUsername=null, sys_dbRights=null, sys_NewRecOwnerGrpID=0 where 1";
    $res1 = mysql_query($query1);
    if (mysql_error())  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to reset sys_dbRegisteredID in sysIdentification table. (".mysql_error().
        ")<br> Please reset the registration ID manually</b></p>";
    }
    //assign origin ID    
    db_register($newname, $sourceRegID);

    // Index new database for Elasticsearch
    //TODO: Needs error report, trap error and warn or abort clone
    buildAllIndices($newname); // ElasticSearch uses full database name including prefix

    // Copy the images and the icons directories
    //TODO: Needs error report, trap error and warn or abort clone
    recurse_copy( HEURIST_UPLOAD_ROOT.HEURIST_DBNAME, HEURIST_UPLOAD_ROOT.$targetdbname );

    // Update file path in target database  with absolute paths
    $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_UPLOAD_ROOT.$targetdbname.
    "/' where ulf_FilePath='".HEURIST_UPLOAD_ROOT.HEURIST_DBNAME."/' and ulf_ID>0";
    $res1 = mysql_query($query1);
    if (mysql_error())  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to set database files path to new path</b>".
        "<br>Query was:".$query1.
        "<br>Please get your system administrator to fix this problem BEFORE editing the database (your edits will affect the original database)</p>";
    }

    // Success!
    echo "<hr><p>&nbsp;</p><h2>New database '$targetdbname' created successfully</h2>";
    print "<p>Please access your new database through this link: <a href='".HEURIST_BASE_URL."?db=".$targetdbname.
    "' title='' target=\"_new\"><strong>".$targetdbname."</strong></a></p>";
    
    return true;
} // straightCopyNewDatabase
?>