<?php
/**
* Main header for all heurist pages.
* It
* 1) initializes System.php
* 2) prints out html header with minimum set of scripts
* 3) init client side hAPI
* 4) apply theme
* 5) load and init localiztion
* 6) calls for user defined onPageInit function that should perform further page init - IMPORTANT
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once dirname(__FILE__).'/../../hserv/System.php';

if(defined('IS_INDEX_PAGE')){
    //from main (index) page it redirects to startup
    $_REQUEST['list'] = 1;
    define('ERROR_REDIR','startup/index.php'); //redirects to startup page - list of all databases
}else{
    if(!defined('PDIR')) define('PDIR','../../');  //need for proper path to js and css
    define('ERROR_REDIR', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');
    
    $isLocalHost = ($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1');
}

$error_msg = '';
$isSystemInited = false;

// init main system class
$system = new System();

if(@$_REQUEST['db']){
    //if database is defined then connect to given database
    $isSystemInited = $system->init(@$_REQUEST['db']);
}

if(!$isSystemInited){
    if(count($system->getError()) > 0){
        $_REQUEST['error'] = $system->getError();
    }
    include_once ERROR_REDIR;
    exit;
}

$login_warning = 'To perform this action you must be logged in';
$invalid_access = true;

$is_admin = $system->is_admin();

//
// to limit access to particular page
// define const in the very begining of your php code  just before require_once '/initPage.php';
//
if(defined('LOGIN_REQUIRED') && !$system->has_access()){
    //No Need to show error message when login is required, login popup will be shown
    //$message = $login_warning
    //include_once ERROR_REDIR;
    exit;
}else if(defined('MANAGER_REQUIRED') && !$is_admin){ //A member should also be able to create and open database
    $message = $login_warning.' as Administrator of group \'Database Managers\'';
    include_once ERROR_REDIR;
    exit;
}else if(defined('OWNER_REQUIRED') && !$system->is_dbowner()){
    $message = $login_warning.' as Database Owner';
    include_once ERROR_REDIR;
    exit;
}else{
    $invalid_access = false;
}

// Check if current user has the necessary permissions
if(!$invalid_access && (defined('CREATE_RECORDS') || defined('DELETE_RECORDS'))){

    $required = '';
    $user_permissions = $system->getCurrentUser()['ugr_Permissions'];

    if(defined('CREATE_RECORDS') && !$user_permissions['add'] && !$is_admin){
        $required = 'create';
    }
    if(defined('DELETE_RECORDS') && !$user_permissions['delete'] && !$is_admin){
        $required .=  $required === '' ? 'delete' : ' and delete';
    }

    if($required !== ''){ $message = "To perform this action you need permission to $required records"; }
}

//verify database version against minimal required
if(defined('IS_INDEX_PAGE')){
    
    $subsubVer = intval($system->get_system('sys_dbSubSubVersion'));
    
    if (version_compare(HEURIST_MIN_DBVERSION,
    $system->get_system('sys_dbVersion').'.'
    .$system->get_system('sys_dbSubVersion').'.'
    .$subsubVer)>0){

        include_once 'admin/setup/dbupgrade/upgradeDatabase.php';
        exit;
    }
}

//$system->defineConstants(); //init constants for record and field types

// BASE tag is convenient however it does not suit
// reason: some jquery widgets uses href (tabcontrol for example)
// <base href="<?php echo PDIR;">
/*
<!doctype html>
<html  class="no-js" lang="en" dir="ltr">
*/
if(true || defined('IS_INDEX_PAGE')){
?>
<!DOCTYPE html>
<?php    
}
?>
<html lang="en">
<head>

<title><?php echo (@$_REQUEST['db']?htmlspecialchars($_REQUEST['db']):'').'. '.HEURIST_TITLE; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
<meta content="telephone=no" name="format-detection">

<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!--
<meta http-equiv="Content-Security-Policy" content="default-src https: data: http: 'unsafe-eval' 'unsafe-inline'; img-src https: data: http:;">
-->
<!--
'self' http://maps.nypl.org -->

<link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

<?php 
// Do not use google analytics unless requested in heuristConfigIni.php
$allowGoogleAnalytics = false; //this is deprecated version of google analytics that will be disabled in June 2024
if($allowGoogleAnalytics && !$isLocalHost) {
    $host = strtolower($_SERVER["SERVER_NAME"]);
    
    if (strpos('heuristref.net', $host===0) 
        || strpos('heuristref', $host)===0) {// Operating on Heurist reference server
        ?>     
        <!-- Heurist Reference Server, Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=UA-131444459-1"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'UA-131444459-1'); 
        </script>
        <?php  
    } else {
        ?>
        <!-- Other Heurist server, Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=UA-132203312-1"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'UA-132203312-1'); 
        </script>
        <?php  

    }
}

$isUpgrade = true;

?>

<script src="https://code.jquery.com/jquery-3.7.1.js"></script>
<script src="https://code.jquery.com/jquery-migrate-3.4.1.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.5.7/jquery.fileupload.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.5.7/jquery.iframe-transport.min.js"></script>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />

<script type="text/javascript" src="<?php echo PDIR;?>external/js/wellknown.js"></script>

<!--
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core.min.js"></script>
 -->

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hRecordSearch.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_query.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_geo.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utilsCollection.js"></script>

<?php if(@$_REQUEST['ll']=='DigitalHarlem' || @$_REQUEST['ll']=='DigitalHarlem1935'){ ?>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/digital_harlem/dh_search_minimal.js"></script>
<!--    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/digital_harlem/google_analytics.js"></script>
-->
<?php } ?>

<!-- CSS -->
<?php include_once dirname(__FILE__).'/initPageCss.php'; ?>

<script type="text/javascript">

    // overwrite the standard jquery show method
    // apply listener in widgets on this page to refresh content on show
    // example
    //        var that = this;
    //        this.element.on("myOnShowEvent", function(event){
    //            if( event.target.id == that.element.attr('id')){
    //                that._refresh();
    //            }
    //        });
    //        this.element.off("myOnShowEvent");
    var orgShow = $.fn.show;
    $.fn.show = function()
    {
        orgShow.apply( this, arguments ); //apply original show
        $(this).trigger( 'myOnShowEvent' );
        return this;
    }

    //
    // overwrite datepicker method
    //

    $.datepicker._gotoToday = function(event){

        var target = $(event),
        inst = this._getInst(target[0]);

        var date = new Date();

        inst.selectedDay = date.getDate();
        inst.drawMonth = inst.selectedMonth = date.getMonth();
        inst.drawYear = inst.selectedYear = date.getFullYear();

        $.datepicker._selectDate(event,
            $.datepicker._formatDate(inst, 
                inst.selectedDay, inst.selectedMonth, inst.selectedYear));

    }


    var onAboutInit, onPageInit, isHapiInited = false;

    // if hAPI is not defined in parent(top most) window we have to create new instance
    $(document).ready(function() {

        // Standalone check
        if(!window.hWin.HAPI4){
            window.hWin.HAPI4 = new hAPI('<?php echo htmlspecialchars($_REQUEST['db'])?>', onHapiInit);
        }else{
            // Not standalone, use HAPI from parent window
            onHapiInit( true );
        }

    });

    //
    // Callback function on hAPI initialization
    //
    function onHapiInit(success)
    {
        //if(isHapiInited) return;

        isHapiInited = true;

        if(success) // Successfully initialized system
        {
            if(!window.hWin.HAPI4.sysinfo['layout']){
                var layoutid = '<?php echo htmlspecialchars(@$_REQUEST['ll']);?>';

                if(window.hWin.HEURIST4.util.isempty(layoutid)){
                    layoutid = "H6Default";
                }
                window.hWin.HAPI4.sysinfo['layout'] = layoutid; //keep current layout
            }

            if(!window.hWin.HEURIST4.util.isnull(onAboutInit) && window.hWin.HUL.isFunction(onAboutInit)){
                if(window.hWin.HAPI4.sysinfo['layout']!='WebSearch')
                    onAboutInit(); //init about dialog
            }

            if(initialLoadDatabaseDefintions(null, onPageInit)){
                return;                
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
            success = false;
        }

        if(window.hWin.HUL.isFunction(onPageInit)){
            onPageInit(success);
        }
    }

    //
    //
    //
    function initialLoadDatabaseDefintions(params, callback){
    
            if($.isEmptyObject(window.hWin.HAPI4.EntityMgr.getEntityData2('defRecTypes'))){ //defintions are not loaded

                var sMsg = 'Cannot obtain database definitions (refreshEntityData function). '
                +'This is probably due to a network timeout. However, if the problem '
                +'persists please report to Heurist developers as it could indicate '
                +'corruption of the database.';                            
                
                //params = {recID:recID} or {rty_ID:rty_ID} - to load defs for particular record or rectype
                var entities = (params)?params:'all'; //'rty,dty,rst,swf';

                window.hWin.HAPI4.EntityMgr.refreshEntityData(entities, function(){
                    if(arguments){                    
                    if(arguments[1]){
                        
                        //verify definitions relevance every 20 seconds
                        if(false){
                            setInterval(function(){window.hWin.HAPI4.EntityMgr.relevanceEntityData()}, 20000);
                        }

                        if(!window.hWin.HEURIST4.util.isnull(callback) && window.hWin.HUL.isFunction(callback)){
                            callback(true);
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(sMsg);
                        if(window.hWin.HUL.isFunction(callback)){ callback(false); }
                    }
                    }
                });
                return true;
            }
            return false;
        
    }
</script>

