<?php

/**
* cloneDatabase.php: Copies an entire database verbatim
*                    Note that the cloning method was changed in 2014, using our own SQL dump fucntion, to give more control
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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


require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../records/index/elasticSearchFunctions.php');
require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');
require_once(dirname(__FILE__).'/../../../common/php/dbScript.php');

if(isForAdminOnly("to clone a database")){
    return;
}

mysql_connection_overwrite(DATABASE);
if(mysql_error()) {
    die("<h2>Error</h2>Sorry, could not connect to the database (mysql_connection_overwrite error)");
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
        </script>


        <div class="banner"><h2>Clone Database</h2></div>

        <script type="text/javascript" src="../../../common/js/utilsLoad.js"></script>
        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>
        <script src="../../../common/php/loadCommonInfo.php"></script>
        <div id="page-inner" style="overflow:auto">

            <p>This function simply copies the current database <b> <?=HEURIST_DBNAME?> </b> to a new one with no changes. <br />
                The new database is identical to the old in all respects including users, access and attaachments <br />
                (beware of making many copies of databases containing many large files, as all uploaded files are copied).<br />
                The target database is unregistered with the Heurist central index even if the source database is registered.
                </p>

            <?php


            // ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

            if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('targetdbname', $_REQUEST)){
                ?>
                <div class="separator_row" style="margin:20px 0;"></div>
                <form name='selectdb' action='cloneDatabase.php' method='get'>
                    <input name='mode' value='2' type='hidden'> <!-- calls the form to select mappings, step 2 -->
                    <input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
                    <p>The database will be created with the prefix <b><?=HEURIST_DB_PREFIX?></b>
                        (all databases created by this installation of the software will have the same prefix).</p>
                    <h3>Enter a name for the cloned database:</h3>
                    <div style="margin-left: 40px;">
                        <input type='text' name='targetdbname' onkeypress="{onKeyPress(event)}"/>
                        <input type='submit' value='Clone "<?=HEURIST_DBNAME?>"'/>
                    </div>

                </form>
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

    // Avoid illegal chars in db name
    $hasInvalid = isInValid($targetdbname);
    if ($hasInvalid) {
        echo ("<p><hr><p>&nbsp;<p>Requested database copy name: <b>$targetdbname</b>".
            "<p>Sorry, only letters, numbers and underscores (_) are allowed in the database name");
        return false;
    } // rejecting illegal characters in db name


    $list = mysql__getdatabases();
    $list = array_map("arraytolower", $list);
    if(in_array(strtolower($targetdbname), $list)){
        echo ("<p class='error'>Error: database '".$targetdbname."' already exists. Choose different name<br/></p>");
        return false;
    }

    cloneDatabase($targetdbname);

    print "</div></body></html>";
}

// ---- COPY FUNCTION -----------------------------------------------------------------

function cloneDatabase($targetdbname) {
    set_time_limit(0);

    $newname = HEURIST_DB_PREFIX.$targetdbname;

    //create new database
    if(!db_create($newname)){
        return false;
    }

    echo_flush ("<p>Create Database Structure (tables)</p>");
    if(db_script($newname, dirname(__FILE__)."/../dbcreate/blankDBStructure.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        return false;
    }

    // Remove chatac
    mysql_connection_insert($newname);
    mysql_query('delete from sysIdentification where 1');
    mysql_query('delete from sysTableLastUpdated where 1');
    mysql_query('delete from sysUsrGrpLinks where 1');
    mysql_query('delete from sysUGrps where ugr_ID>=0');
    mysql_query('delete from defLanguages where 1');


    echo_flush ("<p>Copy data</p>");
    if( db_clone(DATABASE, $newname) ){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        return false;
    }

    //need to successful addition of constraints
    // TODO: what does this mean?
    mysql_query('update defTerms t1 left join defTerms t2 on t1.trm_InverseTermId=t2.trm_ID
        set t1.trm_InverseTermId=null
    where t1.trm_ID>0 and t2.trm_ID is NULL');
    mysql_query('delete FROM usrRecentRecords
        where rre_RecID>0
    and rre_RecID not in (select rec_ID from Records)');

    echo_flush ("<p>Addition Referential Constraints</p>");
    if(db_script($newname, dirname(__FILE__)."/../dbcreate/addReferentialConstraints.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        return false;
    }

    echo_flush ("<p>Addition Procedures and Triggers</p>");
    if(db_script($newname, dirname(__FILE__)."/../dbcreate/addProceduresTriggers.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        db_drop($newname);
        return false;
    }

    mysql_connection_insert($newname);
    // RESET register db ID
    $query1 = "update sysIdentification set sys_dbRegisteredID=0, sys_hmlOutputDirectory=null, sys_htmlOutputDirectory=null where 1";
    $res1 = mysql_query($query1);
    if (mysql_error())  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to reset sys_dbRegisteredID in sysIdentification table. (".mysql_error().
        ")<br> Please reset the registration ID manually</b></p>";
    }

    // Index new database for Elasticsearch
    buildAllIndices($targetdbname);

    // Copy the images and the icons directories
    recurse_copy( HEURIST_UPLOAD_ROOT.HEURIST_DBNAME, HEURIST_UPLOAD_ROOT.$targetdbname );

    // Update file path in target database
    $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_UPLOAD_ROOT.$targetdbname.
    "/' where ulf_FilePath='".HEURIST_UPLOAD_ROOT.HEURIST_DBNAME."/' and ulf_ID>0";
    $res1 = mysql_query($query1);
    if (mysql_error())  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to set files path to new path</b>".
        "<br>Query was:".$query1.
        "<br>Please consult the system administrator</p>";
    }

    // Success!
    echo "<hr><p>&nbsp;</p><h2>New database '$targetdbname' created successfully</h2>";
    print "<p>Please go to the <a href='".HEURIST_BASE_URL."admin/adminMenu.php?db=".$targetdbname.
    "' title='' target=\"_new\"><strong>administration page</strong></a>, to configure your new database</p>";

} // straightCopyNewDatabase
?>