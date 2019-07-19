<?php

/**
* deleteCurrentDB.php Deletes the current database (owner group admins only)
*                     Note that deletion of multiple DBs in dbStatistics.php uses deleteDB.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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
require_once(dirname(__FILE__).'/../../../records/index/elasticSearch.php');
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>Delete Current Heurist Database</title>
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>" /> <!-- theme css -->
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
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
        </style>
    </head>
    <body class="popup">
<?php
    //owner can delete without password
    $pwd = @$_REQUEST['pwd'];
    if(!$system->is_dbowner() && $system->verifyActionPassword($pwd, $passwordForDatabaseDeletion) ){
            $err = $system->getError();
            print '<div class="ui-state-error">'.$err['message'].'</div>';
    }else{

            $dbname = $_REQUEST['db'];

            if(!@$_REQUEST['mode']) {
                ?>
        <div class='banner'><h2>Deleting Current Heurist Database</h2></div>
            
        <div id="wait_p" class="loading" style="width:100%;height:320px;display:none">
            <i>Please wait for confirmation message (may take a couple of minutes for large databases)</i>
        </div>
        
        <div id='page-inner' style='overflow:auto'>
        
                <div class="gray-gradient" style="display:inline-block;">
                    <span class="ui-icon ui-icon-alert" style="display:inline-block;color:red;text-align:center"></span>&nbsp;
                    DANGER 
                    &nbsp;<span class="ui-icon ui-icon-alert" style="display:inline-block;color:red"></span>
                </div>
                <h1 style='display:inline-block;font-size: 16px;'>DELETION OF CURRENT DATABASE</h1><br>
                <h3>This will PERMANENTLY AND IRREVOCABLY delete the current database: </h3>
                <h2>About to delete database: <?=$dbname?></h2>
                <form name='deletion' action='deleteCurrentDB.php' method="post">
                    <p>Enter the words DELETE MY DATABASE below in ALL-CAPITALS to confirm that you want to delete the current database
                    <p>Type the words above to confirm deletion <input type='input' maxlength='20' size='20' name='del' id='del'>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <input type='button' class="h3button" value='OK to Delete' style='font-weight: bold;' 
                        onclick="$('#page-inner').hide(); $('#wait_p').show(); forms[0].submit();">
                    <input name="pwd" value="<?php echo $pwd;?>" type='hidden'>
                    <input name='mode' value='2' type='hidden'>
                    <input name='db' value='<?=$dbname?>' type='hidden'>
                </form>
        </div>
                <?php
            }else if(@$_REQUEST['mode']=='2') {

                if (@$_REQUEST['del']=='DELETE MY DATABASE') {
                    
                    if ($dbname=='') {
                        print "<div class='ui-state-error'>Undefined database name</div>"; // shouldn't get here
                    } else {
                        
                        //not verbose, copy files, don't dump data
                        if(DbUtils::databaseDrop(false, $dbname, true, false)){ 

                            //print "<p>Removing indexes<br /><br /></p>";
                            ElasticSearch::deleteIndexForDatabase($dbname);  //Deleting all Elasticsearch indexes
                            //show modal popup for furhter redirect to browse databases
?>
<script>    
$(document).ready(
function () {
    window.hWin.HEURIST4.msg.showMsgDlg(
        '<h2>Database <b><?=$dbname?></b> has been deleted</h2>'
       + '<p>Associated files stored in upload subdirectories have been archived and moved to "DELETED_DATABASES" folder.</p>'
       + '<p>If you delete databases with a large volume of data, please ask your system administrator to empty this folder.</p>',
       null, 'Database deleted',
       {
            width:700,
            height:180,
            close: function(){
                window.hWin.document.location = window.hWin.HAPI4.baseURL; //+ "hsapi/utilities/list_databases.php";
            }
       }
    );
}
);
</script>
<?php                            
                            
                        }else{
                            $err = $system->getError();
                            $message = @$err['message'];
?>                            
        <div style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">
            <div class="ui-state-error" 
                style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon ui-icon-alert" style="float: left; margin-right:.3em;font-weight:bold"></span>
                Unable to delete <b> <?php echo $dbname;?></b>. <?php echo $message;?>
                <p>Check that the database still exists. <?php echo CONTACT_HEURIST_TEAM;?> if needed<br></p>
            </div>
        </div>
<?php                            
                        }
                    }
                } // delete database

                else { // didn't request properly
                    print "<div class='ui-state-error'><h2>Request disallowed</h2>Incorrect challenge words entered. Database <b>$dbname</b> was not deleted</div>";
                }
            } // request mode 2
    }
            ?>
    </body>
</html>





