<?php

    /**
    * File: clearCurrentDB.php Deletes all records/details/bookmarks from the current database (owner group admins only)
    * Does not affect definitions. Resets record counter to 0.
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.0
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
require_once(dirname(__FILE__).'/../../../records/index/elasticSearch.php');
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Clear Records from Current Heurist Database</title>
    <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
    <style>
    p {
        display: block;
        -webkit-margin-before: 1em;
        -webkit-margin-after: 1em;
        -webkit-margin-start: 0px;
        -webkit-margin-end: 0px;
    }
    .gray-gradient {
        background-color: rgba(100, 100, 100, 0.6);
        background: -moz-linear-gradient(center top , rgba(100, 100, 100, 0.6), rgba(100, 100, 100, 0.9)) repeat scroll 0 0 transparent;
        background: -webkit-gradient(linear, left top, left bottom, from(rgba(100, 100, 100, 0.6)), to(rgba(100, 100, 100, 0.9)));
        border: 1px solid #999;
        -moz-border-radius: 3px;
        -webkit-border-radius: 3px;
        border-radius: 3px;
        padding: 3px;
        font-size: 14px;
        color: #FFF;
    }
    h2, h3{
        margin:0;
    }
    </style>
</head>
<body class='popup'>
    <div class='banner'><h3>Clear Records from Current Heurist database</h3></div>
    <div id='page-inner' style='overflow:auto'>
<?php
    //owner can delete without password
    if(!$system->is_dbowner() && $system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForDatabaseDeletion) ){
            print '<div class="ui-state-error">'.$response = $system->getError()['message'].'</div>';
    }else{

    $dbname = $_REQUEST['db'];

    if(!@$_REQUEST['mode']) {        //dialog if mode set this is action
?>

    <div class="gray-gradient" style="display:inline-block;">
            <span class="ui-icon ui-icon-alert" style="display:inline-block;color:red;text-align:center"></span>&nbsp;
            DANGER 
            &nbsp;<span class="ui-icon ui-icon-alert" style="display:inline-block;color:red"></span>
    </div>

    <h1 style='display:inline-block;font-size: 16px;'>CLEAR ALL RECORDS FROM CURRENT DATABASE</h1><br>

    <h3>This will clear (delete) all records and reset counter to 1 for the current database: </h3>
    <h2>Clear database: <?=$dbname?></h2>
    <form name='deletion' action='clearCurrentDB.php' method='post'>
        <p>Database definitions - record types, fields, terms, tags, users etc. - are not affected.
        Uploaded files are not deleted. Bookmarks and tags on specific records are deleted.<p>
        This operation may take some minutes on a large database<br>
        <p>Enter the words CLEAR ALL RECORDS in ALL-CAPITALS to confirm that you want to clear all records from the current database
        <p>Type the words above to confirm deletion of records: <input type='input' maxlength='20' size='20' name='del' id='del'>
        &nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='OK to Clear' class="h3button" style='font-weight: bold;' >
        <input name="pwd" value"<?php echo @$_REQUEST['pwd'];?>" type="hidden">
        <input name='mode' value='2' type='hidden'>
        <input name='db' value='<?=$dbname?>' type='hidden'>
    </form>
<?php

    }else if(@$_REQUEST['mode']=='2') {

        if (@$_REQUEST['del']=='CLEAR ALL RECORDS') {
            print "<br/><hr>";
            if ($dbname=='') {
                print "<p>Undefined database name</p>"; // shouldn't get here
            } else {

                $res = DbUtils::databaseEmpty($dbname);

                if (!$res) {
                    echo ("<h2>Warning:</h2> Unable to fully delete records from <b>".HEURIST_DB_PREFIX.$dbname."</b>");
                    //print "<p><a href=".HEURIST_BASE_URL."?db=$dbname>Return to Heurist</a></p>";
                } else {
                    // Remove from ElasticSearch
                    print "<br/><br/>Removing indexes, calling deleteIndexForDatabase with parameter $dbname";
                    ElasticSearch::deleteIndexForDatabase($dbname); // ElasticSearch uses full database name with prefix

                    print "<br/><br/>Record data, bookmarks and tags have been deleted from <b>$dbname</b><br/>";
                    print "Database structure (record types, fields, terms, constraints etc.) and users have not been affected.";
                    //print "<p><a href=".HEURIST_BASE_URL."?db=$dbname>Return to the database home page</a></p>";
                }
            }
        }
        else { // didn't request properly
            print "<p class='ui-state-error'><h2>Request disallowed</h2>Incorrect challenge words entered. Data was not deleted from <b>$dbname</b></p>";
        }
    }
    }
?>
 </div>
 </body>
</html>

