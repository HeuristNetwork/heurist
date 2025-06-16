<?php
/**
* initPageLogin.php - Handles the initialization of page that require user login
*
* This script sets up a minimal HTML page, includes core JavaScript libraries for HAPI (Heurist API)
* and login functionalities, and then initiates a forceful login prompt if the user is not already authenticated
* or does not meet the required access level for the page. It's typically used for pages that
* should not be accessed by unauthenticated users.
*
* @package     Heurist academic knowledge management system
* @subpackage  hclient\framecontent
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
require_once 'initPageMin.php';//without client hapi

if(!@$_REQUEST['db']){
    $message = '<b>Required</b> database parameter >>> is not defined';
    include_once ERROR_REDIR;
    exit;
}

/*
Workflow:
loads main page for logo, icon, banner, style
*/

$system->defineConstants();
$hasAccess = ($system->isAdmin());
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
<head>
    <title><?php echo HEURIST_TITLE; ?></title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="robots" content="noindex,nofollow">

<?php
    includeJQuery();
?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
    <script>window.hWin = window;</script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profileLogin.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profileEdit.js"></script>
<?php
    include_once dirname(__FILE__).'/initPageCss.php';

    $dbname = @$_REQUEST['db'];
    $dbname = (preg_match('[\W]', $dbname))?'':$dbname;
?>
<script>
var login_warning = ''; // Holds any warning message related to login.
var requiredLevel = 0;  // Specifies the required access level: 0 for any logged-in user, 1 for admin, 2 for owner.
var database = '<?php echo htmlspecialchars($dbname);?>'; // The current database name.
//
//
//
/**
 * Callback function executed after HAPI (Heurist API) is initialized.
 * If HAPI initialization fails, it shows an error message.
 * Otherwise, it populates HAPI sysinfo with database statistics
 * and then calls verify_credentials.
 *
 * @param {boolean} success - Indicates whether HAPI initialization was successful.
 */
function onHapiInit(success){

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr({
            message: 'Cannot initialize system on client side. '
                    +`Database${database}, please consult Heurist developers`,
            error_title: 'Unable to initialise Heurist'
        });
            return;
    }

<?php
     //returns total records in db and counts of active entries in dashboard
     list($db_total_records, $db_has_active_dashboard, $db_workset_count) = $system->getTotalRecordsAndDashboard();
     echo 'window.hWin.HAPI4.sysinfo.db_total_records = '.$db_total_records.';';
     echo 'window.hWin.HAPI4.sysinfo.db_has_active_dashboard = '.$db_has_active_dashboard.';';
     echo 'window.hWin.HAPI4.sysinfo.db_workset_count = '.$db_workset_count.';';
?>

    verify_credentials( false );
}

/**
 * Verifies if the current user has the required access level.
 * If the user does not have access, it constructs a message and
 * either shows a warning dialog (if show_warning is true) or
 * directly shows the login dialog.
 *
 * @param {boolean} show_warning - If true, a warning dialog is shown before the login dialog.
 *                                 If false, the login dialog is shown directly.
 */
function verify_credentials( show_warning ){

    if(window.hWin.HAPI4.has_access(requiredLevel)){

    }else{

        msg = 'To perform this operation you have to be logged in (you may have been logged out due to lack of activity - if so, please reload the page)';

        if(requiredLevel==window.hWin.HAPI4.sysinfo.db_managers_groupid){
           msg += ' as database administrator';// of group "Database Managers"'
        }else if(requiredLevel==2){
           msg += ' as database onwer';
        //}else if(requiredLevel!=0){

        }
        if(msg!=''){
            var win_mappreview = window.parent.hWin;
            if(show_warning){

                var $dlg2 = win_mappreview.HEURIST4.msg.showMsgDlg(msg+'<br> Database: '+window.hWin.HAPI4.database,
                    {OK:
                    function(){

                        $dlg2.dialog( "close" );

                        showLoginDialog(false, function( is_logged ) {

                        }, win_mappreview, 'heurist-clearinghouse-login-dialog');
                    }});
            }else{
                //show login dialog at once
                showLoginDialog(false, function(is_logged){

                        }, win_mappreview, 'heurist-clearinghouse-login-dialog');
            }


        }
    }
}

//
//init hapi
//
/**
 * Executes when the HTML document is fully loaded and parsed.
 * Initializes the HAPI (Heurist API) interface with the current database
 * and sets onHapiInit as the callback function.
 */
$(document).ready(function() {
    window.hWin.HAPI4 = new hAPI(database, onHapiInit);
});
</script>
</head>
<body>
</body>
</html>

