<?php

/**
* deleteCurrentDB.php Deletes the current database (owner group admins only)
*                     Note that deletion of multiple DBs in dbStatistics.php uses deleteDB.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
require_once(dirname(__FILE__).'/../../../records/index/elasticSearchFunctions.php');
require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');

if(isForOwnerOnly("to delete a database")){
    return;
}
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>Delete Current Heurist Database</title>
        <link rel='stylesheet' type='text/css' href='../../../common/css/global.css'>
        <link rel='stylesheet' type='text/css' href='../../../common/css/edit.css'>
        <link rel='stylesheet' type='text/css' href='../../../common/css/admin.css'>
    </head>
    <body class='popup'>
        <div class='banner'><h2>Delete Current Heurist Database</h2></div>
        <div id='page-inner' style='overflow:auto'>
            
            <?php
            $dbname = $_REQUEST['db'];


            if(!@$_REQUEST['mode']) {
                ?>
                <h4 style='display:inline-block; margin:0 5px 0 0'><span><img src='../../../common/images/url_error.png' />
                    DANGER <img src='../../../common/images/url_error.png' /></span></h4>
                <h1 style='display:inline-block'>DELETION OF CURRENT DATABASE</h1><br>
                <h3>This will PERMANENTLY AND IRREVOCABLY delete the current database: </h3>
                <h2>About to delete database: <?=$dbname?></h2>
                <form name='deletion' action='deleteCurrentDB.php' method='get'>
                    <p>Enter the words DELETE MY DATABASE below in ALL-CAPITALS to confirm that you want to delete the current database
                    <p>Type the words above to confirm deletion <input type='input' maxlength='20' size='20' name='del' id='del'>
                    &nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='OK to Delete' style='font-weight: bold;' >
                    <input name='mode' value='2' type='hidden'>
                    <input name='db' value='<?=$dbname?>' type='hidden'>
                </form>
                <?php
            }else if(@$_REQUEST['mode']=='2') {

                if (@$_REQUEST['del']=='DELETE MY DATABASE') {
                    print "<br/><br/><hr>";
                    if ($dbname=='') {
                        print "<p class='error'>Undefined database name</p>"; // shouldn't get here
                    } else {
                        // It's too risky to delete data with "rm -Rf .$uploadPath", could end up trashing stuff needed elsewhere, so we move it
                        $uploadPath = HEURIST_UPLOAD_ROOT.$dbname; // individual deletio nto avoid risk of unintended disaster with -Rf
                        $cmdline = "mv ".$uploadPath." ".HEURIST_UPLOAD_ROOT."DELETED_DATABASES";
                        $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                        if ($res2 != 0 ) {
                            echo ("<h2>Warning:</h2> Unable to move <b>$uploadPath</b> to the deleted files folder, perhaps a permissions problem or previously deleted.");
                            echo ("<p>Please ask your system adminstrator to delete this folder if it exists.<br></p>");
                            echo($output2);
                        }
                        if (!db_drop(HEURIST_DB_PREFIX.$dbname, false)) {
                            echo ("<h2>Warning:</h2> Unable to delete <b>".HEURIST_DB_PREFIX.$dbname."</b>");
                            echo ("<p>Check that the database still exists. Consult Heurist helpdesk if needed<br></p>");
                            //echo($output2);
                        } else {
                            // Remove from Elasticsearch
                            print "<p>Removing indexes, calling deleteIndexForDatabase with parameter $dbname<br /><br /></p>";
                            deleteIndexForDatabase($dbname);  //Deleting all Elasticsearch indexes
                            ?>
                            <h2>Database <b><?=$dbname?></b> has been deleted</h2>
                            <p>Associated files stored in upload subdirectories <b><?=$uploadPath?></b> <br/> have ben moved to <?=HEURIST_UPLOAD_ROOT?>DELETED_DATABASES.</p>
                            <p>If you delete databases with a large volume of data, please ask your system administrator to empty this folder.</p>
                            <p><a href='#' onclick='{top.location.href="<?=HEURIST_BASE_URL?>index.php" }' >Return to Heurist</a></p>
                            <?php
                        }
                    }
                } // delete database

                else { // didn't request properly
                    print "<p><h2>Request disallowed</h2>Incorrect challenge words entered. Database <b>$dbname</b> was not deleted</p>";
                }
            } // request mode 2
            ?>
        </div>
    </body>
</html>





