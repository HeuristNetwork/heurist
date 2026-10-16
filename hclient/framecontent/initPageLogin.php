<?php
/**
* initPageLogin.php - Handles the initialization of page that require user login
*
* This script sets up a minimal HTML page, includes core JavaScript libraries for HAPI (Heurist API)
* and login functionalities, and then initiates a login prompt if the user is not already authenticated.
*
* It can be loaded in an iframe by HEURIST4.ui.checkAndLoginInFrame(). In that case the parent
* window passes a callback function to this frame via assignLoginCallback(). The login dialog itself
* is created inside this iframe, isolating it from CSS on a user's custom website.
*
* @project     Heurist academic knowledge management system
* @package  hclient\framecontent
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

$system->defineConstants();
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
    $dbname = (preg_match('/[\W]/', $dbname))?'':$dbname;
    $isforced = (@$_REQUEST['isforced']==1 || @$_REQUEST['isforced']==='true') ? 1 : 0;
?>
<script>
var login_warning = '';
var requiredLevel = 0;
var database = '<?php echo htmlspecialchars($dbname, ENT_QUOTES);?>';
var isForcedLogin = <?php echo intval($isforced); ?>;

// These values are assigned by the parent iframe onload handler.
var parentLoginCallback = null;
var parentLoginWindow = null;

/**
 * Called by parent HEURIST4.ui.checkAndLoginInFrame() after iframe load.
 *
 * @param {?function} callback callback to execute in parent context
 * @param {?Window} parentwin parent Heurist window; defaults to parent.hWin/window.parent
 * @param {boolean=} isforced whether login is forced
 */
function assignLoginCallback(callback, parentwin, isforced){
    parentLoginCallback = (typeof callback === 'function') ? callback : null;
    parentLoginWindow = parentwin || (window.parent && (window.parent.hWin || window.parent)) || null;
    if(typeof isforced !== 'undefined'){
        isForcedLogin = isforced ? 1 : 0;
    }
}

function notifyParentLoginFinished(is_logged){

    var isLogged = !!is_logged;
    var pwin = parentLoginWindow || (window.parent && (window.parent.hWin || window.parent));

    // Keep the parent HAPI object in sync with the login result in this iframe.
    // The session cookie is already set by the login request; this only updates client-side state.
    try{
        if(isLogged && pwin && pwin.HAPI4 && window.hWin.HAPI4){
            if(!pwin.HAPI4.database || pwin.HAPI4.database === window.hWin.HAPI4.database){
                pwin.HAPI4.setCurrentUser(window.hWin.HAPI4.currentUser);
                pwin.HAPI4.sysinfo = window.hWin.HAPI4.sysinfo;
                if(pwin.jQuery && pwin.HAPI4.Event){
                    pwin.jQuery(pwin.document).trigger(pwin.HAPI4.Event.ON_CREDENTIALS, [pwin.HAPI4.currentUser]);
                }
            }
        }
    }catch(e){
        if(window.console) console.error(e);
    }

    if(parentLoginCallback){
        try{
            parentLoginCallback.call(pwin || window.parent, isLogged);
        }catch(e){
            if(window.console) console.error(e);
        }
    }

    if(window.parent && window.parent !== window && window.parent.HEURIST4
            && window.parent.HEURIST4.ui
            && typeof window.parent.HEURIST4.ui.closeLoginFrame === 'function'){
        window.parent.HEURIST4.ui.closeLoginFrame(isLogged);
    }

    if(isForcedLogin && !isLogged && pwin && pwin.HAPI4){
        pwin.location = pwin.HAPI4.baseURL;
    }
}

function onHapiInit(success){

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr({
            message: 'Cannot initialize system on client side. Database '+database+
                     ' most likely because of outdated version number in bookmarked URL. Consult Heurist developers if necessary.',
            error_title: 'Unable to initialise Heurist'
        });
        notifyParentLoginFinished(false);
        return;
    }

<?php
     list($db_total_records, $db_has_active_dashboard, $db_workset_count) = $system->getTotalRecordsAndDashboard();
     echo 'window.hWin.HAPI4.sysinfo.db_total_records = '.$db_total_records.';';
     echo 'window.hWin.HAPI4.sysinfo.db_has_active_dashboard = '.$db_has_active_dashboard.';';
     echo 'window.hWin.HAPI4.sysinfo.db_workset_count = '.$db_workset_count.';';
?>

    verify_credentials(false);
}

function verify_credentials(show_warning){

    if(window.hWin.HAPI4.has_access(requiredLevel)){
        notifyParentLoginFinished(true);
        return;
    }

    var msg = 'To perform this operation you have to be logged in'
            + ' (you may have been logged out due to lack of activity - if so, please reload the page)';

    if(requiredLevel==window.hWin.HAPI4.sysinfo.db_managers_groupid){
       msg += ' as database administrator';
    }else if(requiredLevel==2){
       msg += ' as database owner';
    }

    function openLoginDialog(){
        // Important: parentwin is window, not window.parent.hWin.
        // This keeps the login DOM and CSS inside this iframe.
        showLoginDialog(!!isForcedLogin, function(is_logged){
            notifyParentLoginFinished(is_logged);
        }, window, 'heurist-login-dialog');
    }

    if(show_warning){
        var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg(msg+'<br> Database: '+window.hWin.HAPI4.database,
            {OK: function(){
                $dlg2.dialog('close');
                openLoginDialog();
            }});
    }else{
        openLoginDialog();
    }
}

$(document).ready(function() {
    if(database || !window.hWin?.HAPI4){
        window.hWin.HAPI4 = new hAPI(database, onHapiInit);
    }else{
        verify_credentials(false);
    }
});
</script>
<style>
    html, body { width:100%; height:100%; margin:0; overflow:hidden; }
</style>
</head>
<body>
</body>
</html>
