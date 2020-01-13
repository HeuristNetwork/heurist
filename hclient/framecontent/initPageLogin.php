<?php

/**
*  Init page with minimal client (HAPI) and forceful login
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

require_once(dirname(__FILE__).'/initPageMin.php'); //without client hapi

/*
Workflow:
loads main page for logo, icon, banner, style


*/

$system->defineConstants();
$hasAccess = ($system->is_admin());
?>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">

<?php
if (($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'))  {
?>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
<?php
}else{
?>
    <script src="https://code.jquery.com/jquery-1.12.2.min.js" integrity="sha256-lZFHibXzMHo3GGeehn1hudTAP3Sc0uKXBXAzHX1sjtk=" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
<?php
}
?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
    <script>window.hWin = window;</script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
  
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/localization.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_login.js"></script>   
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_edit.js"></script>   
<?php
    include dirname(__FILE__).'/initPageCss.php'; 
?>    
<script>  
var login_warning = ''
var requiredLevel = 0; //1-admin, 2-owner, 0 logged in
//
//
//
function onHapiInit(success){   
    
    if(!success){    
        window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side. '
            +'Database <?php echo $_REQUEST['db']?>, please consult Heurist developers');
            return;
    }
    
<?php
     //returns total records in db and counts of active entries in dashboard  
     list($db_total_records, $db_has_active_dashboard) = $system->getTotalRecordsAndDashboard(); 
     echo 'window.hWin.HAPI4.sysinfo.db_total_records = '.$db_total_records.';';
     echo 'window.hWin.HAPI4.sysinfo.db_has_active_dashboard = '.$db_has_active_dashboard.';';
?>
    
    verify_credentials( false );
}

function verify_credentials( show_warning ){
    
    if(window.hWin.HAPI4.has_access(requiredLevel)){ 
        
    }else{
        
        msg = 'To perform this operation you have to be logged in';
        
        if(requiredLevel==window.hWin.HAPI4.sysinfo.db_managers_groupid){
           msg += ' as database administrator';// of group "Database Managers"' 
        }else if(requiredLevel==2){
           msg += ' as database onwer';
        //}else if(requiredLevel!=0){
        //   msg = ''; 
        }
        if(msg!=''){
            /*
            $(window).on("beforeunload",  function() { 
                    console.log('beforeunload initPageLogin');
            });
            */
            var win_mappreview = window.parent.hWin;
            if(show_warning){
                
                var $dlg2 = win_mappreview.HEURIST4.msg.showMsgDlg(msg+'<br> Database: '+window.hWin.HAPI4.database,
                    {OK:
                    function(){
                        //$dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                        $dlg2.dialog( "close" );
                        //window.hWin.HEURIST4.msg.showMsgErr(msg+'<br> Database: '+window.hWin.HAPI4.database);            
                        doLogin(false, function( is_logged ) {
                            //window.hWin.HAPI4.verify_credentials(function(){}, login_level_req);
                        }, win_mappreview, 'heurist-clearinghouse-login-dialog');
                    }}); 
            }else{
                //show login dialog at once
                doLogin(false, function(is_logged){
                            //window.hWin.HAPI4.verify_credentials( true );
                        }, win_mappreview, 'heurist-clearinghouse-login-dialog');
            }
            
            
        }
    }        
}

var gtag = null;//google log - DO NOT REMOVE

//
//init hapi    
//
$(document).ready(function() {
    window.hWin.HAPI4 = new hAPI('<?php echo $_REQUEST['db']?>', onHapiInit);
});    
</script>    
</head>
<body>
</body>
</html>

