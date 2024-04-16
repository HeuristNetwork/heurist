<?php

/**
* deleteCurrentDB.php Deletes the current database (owner group admins only)
*                     Note that deletion of multiple DBs in dbStatistics.php uses deleteDB.php
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

require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../../hserv/utilities/dbUtils.php';
require_once dirname(__FILE__).'/../../../hserv/records/indexing/elasticSearch.php';
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>Delete Current Heurist Database</title>
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        
        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../../hclient/framecontent/initPageCss.php'; ?>
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
            background: -moz-linear-gradient(top , rgba(100, 100, 100, 0.6), rgba(100, 100, 100, 0.9)) repeat scroll 0 0 transparent;
            background: -webkit-gradient(linear, left top, left bottom, from(rgba(100, 100, 100, 0.6)), to(rgba(100, 100, 100, 0.9)));
            background-color: rgba(100, 100, 100, 0.6);
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
                ?>
        <div class='banner'><h2 style="margin:0">Deleting Current Heurist Database</h2></div>
            
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
                <h2>About to delete database: <?php echo htmlspecialchars($dbname);?></h2>

                <label><input type='radio' checked name="db-archive" value="zip">Zip all database files</label>
                <label><input type='radio' name="db-archive" value="">Do not archive</label><br>
                <!-- 
                <label><input type='radio' name="db-archive" value="tar">BZip all database files</label>
                <span class="heurist-helper2">BZip is more efficient for archiving but may be slower if there are lot of images</span>
                 -->
                
                <p>Enter the words DELETE MY DATABASE below in ALL-CAPITALS to confirm that you want to delete the current database
                <p>Type the words above to confirm deletion <input type='input' maxlength='20' size='20' name='del' id='db-password'>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                <input type='button' class="h3button" id="btnDelete" value='OK to Delete' style='font-weight: bold;'>
        </div>
<script>    
$(document).ready(
function () {
    
$('#btnDelete').on({click:function(){
    
    if(!$('#page-inner').is(':visible')) return;
    
    if($('#db-password').val()==''){
        return;  
    } 
    
    $('#page-inner').hide();
    $('#wait_p').show();
    
    var url = window.hWin.HAPI4.baseURL+'admin/setup/dboperations/deleteDB.php';
    var request = {pwd: $('#db-password').val(), 
                   db: window.hWin.HAPI4.database,
                   database: window.hWin.HAPI4.database   //database to be deleted
                   };

    let arch_mode = $('input[name=db-archive]:checked').val();
    if(arch_mode){
        request['create_archive'] = arch_mode;
    }
  
    //show wait screen
    window.hWin.HEURIST4.msg.bringCoverallToFront(null, null, 'Deleting Current Heurist Database...');
                   
    window.hWin.HEURIST4.util.sendRequest(url, request, null,
        function(response){
            $('#wait_p').hide();
            window.hWin.HEURIST4.msg.sendCoverallToBack(true);
            
            if(response.status == window.hWin.ResponseStatus.OK){
                
                    var msgAboutArc = '';
                    if($('input[name=db-archive]:checked').val()!=''){
                       msgAboutArc = '<p>Associated files stored in upload subdirectories have been archived and moved to "DELETED_DATABASES" folder.</p>'
                       + '<p>If you delete databases with a large volume of data, please ask your system administrator to empty this folder.</p>';                        
                    }
                
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        '<h3 style="margin:0">Database <b>'+window.hWin.HAPI4.database+'</b> has been deleted</h3>'+msgAboutArc
                       ,null, 'Database deleted',
                       {
                            width:700,
                            height:'auto',
                            close: function(){
                                //redirects to startup page - list of all databases
                                window.hWin.document.location = window.hWin.HAPI4.baseURL; //startup page
                            }
                       }
                    );                
            }else{
                $('#page-inner').show();
                window.hWin.HEURIST4.msg.showMsgErr(response, false);
                
            }
        }, null, 600000  //timeout 10 minutes
    );
}
});    
});
</script>                
                <?php
    }
            ?>
    </body>
</html>





