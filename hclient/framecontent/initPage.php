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
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

require_once(dirname(__FILE__)."/../../hserver/System.php");

if(defined('IS_INDEX_PAGE')){
    //if PDIR is defined this script is main (root)
    define('ERROR_REDIR','hserver/utilities/list_databases.php');
}else{
    if(!defined('PDIR')) define('PDIR','../../');
    define('ERROR_REDIR', HEURIST_BASE_URL.'hclient/framecontent/errorPage.php?db='.@$_REQUEST['db']);
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
    if(defined('IS_INDEX_PAGE')){
        require (ERROR_REDIR);
    }else{
        $err = $system->getError();
        $error_msg = @$err['message']?$err['message']:'';
        header('Location: '.ERROR_REDIR.'&msg='.rawurlencode($error_msg));
    }
    exit();
}

$login_warning = 'To perform this action you must be logged in';

//
// to limit access to particular page
// define const in the very begining of your php code  just before require_once(dirname(__FILE__)."/initPage.php");
//
if(defined('LOGIN_REQUIRED') && !$system->has_access()){
    //header('Location: '.ERROR_REDIR); //'&msg='.rawurlencode($login_warning)); //No Need to show error message when login is required, login page ia already rendered
    exit();


}else if(defined('MANAGER_REQUIRED') && !$system->is_admin() ){ //A member should also be able to create and open database
    header('Location: '.ERROR_REDIR.'&msg='.rawurlencode($login_warning.' as Administrator of group \'Database Managers\''));
    exit();
}else if(defined('OWNER_REQUIRED') && !$system->is_dbowner()){
    header('Location: '.ERROR_REDIR.'&msg='.rawurlencode($login_warning.' as Database Owner'));
    exit();
}

//$system->defineConstants(); //init constants for record and field types

$user = $system->getCurrentUser();
$layout_theme = @$user['ugr_Preferences']['layout_theme'];
if(!$layout_theme) $layout_theme = 'heurist';

if($layout_theme=="heurist" || $layout_theme=="base"){
    //default BASE or HEURIST theme
    $cssLink = PDIR.'ext/jquery-ui-themes-1.12.1/themes/'.$layout_theme.'/jquery-ui.css';
}else{
    //load one of standard themes from jquery web resource
    $cssLink = 'https://code.jquery.com/ui/1.12.1/themes/'.$layout_theme.'/jquery-ui.css';
}

// BASE tag is convenient however it does not suit
// reason: some jquery widgets uses href (tabcontrol for example)
// <base href="<?php echo PDIR;">

?>
<html>
<head>
<title><?=(@$_REQUEST['db']?$_REQUEST['db']:'').'. '.HEURIST_TITLE ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
<meta content="telephone=no" name="format-detection">


<link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

<script>
    var _time_debug = new Date().getTime() / 1000;
    var _time_start = _time_debug;
    //console.log('ipage start');

    //find heurist object in parent windows or init new one if current window is a top most
    function _detectHeurist( win ){
        if(win.HEURIST4){ //defined
            return win;
        }

        try{
            win.parent.document;
        }catch(e){
            // not accessible - this is cross domain
            return win;
        }
        if (win.top == win.self) {
            //we are in frame and this is top most window and Heurist is not defined
            //lets current window will be heurist window
            return window;
        }else{
            return _detectHeurist( win.parent );
        }
    }
    //detect wether this window is top most or inside frame
    window.hWin = _detectHeurist(window);

    /*if (window.top != window.self) {
    //this is frame
    } else {
    //top most window
    window.h4win = window.top;
    }*/


</script>

<?php
if($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
    ?>
    <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-ui-1.12.1/jquery-ui.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-file-upload/js/jquery.iframe-transport.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-file-upload/js/jquery.fileupload.js"></script>
    <?php
}else{
    ?>
    <script src="https://code.jquery.com/jquery-1.12.2.min.js" integrity="sha256-lZFHibXzMHo3GGeehn1hudTAP3Sc0uKXBXAzHX1sjtk=" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>

    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.5.7/jquery.fileupload.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.5.7/jquery.iframe-transport.min.js"></script>
    <?php
}
?>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>ext/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />


<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>

<!-- for debug  remark it and use getMultiScripts for production -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/search_minimal.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>

<!-- loaded by demand script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/digital_harlem/dh_search_minimal.js"></script -->

<link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>" /> <!-- theme css -->
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />  <!-- base css -->

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
    // overwrite datepciker method
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
    

    var onAboutInit, onPageInit;


    // if hAPI is not defined in parent(top most) window we have to create new instance
    $(document).ready(function() {

        //console.log('ipage doc ready '+(window.hWin.HAPI4)+'    '+(new Date().getTime() / 1000 - _time_debug));
        _time_debug = new Date().getTime() / 1000;

        // Standalone check
        if(!window.hWin.HAPI4){
            // In case of standalone page
            //load minimum set of required scripts
            $.getMultiScripts(['localization.js'/*, , 'utils_msg.js'
                'utils_ui.js', 'search_minimal.js', 'recordset.js', 'hapi.js'*/], '<?php echo PDIR;?>hclient/core/')
            .done(function() {
                // all done
                window.hWin.HAPI4 = new hAPI('<?php echo $_REQUEST['db']?>', onHapiInit);

            }).fail(function(error) {
                // one or more scripts failed to load
                onHapiInit(false);

            }).always(function() {
                // always called, both on success and error
            });

        }else{
            // Not standalone, use HAPI from parent window
            onHapiInit( true );
        }

    });

    // Callback function on hAPI initialization
    function onHapiInit(success)
    {
        if(success) // Successfully initialized system
        {
            applyTheme();

            //console.log('ipage hapi inited  '+(new Date().getTime() / 1000 - _time_debug));
            _time_debug = new Date().getTime() / 1000;

            if(!window.hWin.HEURIST4.rectypes){

                if(!window.hWin.HEURIST4.util.isnull(onAboutInit) && $.isFunction(onAboutInit)){
                    onAboutInit();
                }

                window.hWin.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        window.hWin.HEURIST4.rectypes = response.data.rectypes;
                        window.hWin.HEURIST4.terms = response.data.terms;
                        window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr('Cannot obtain database definitions (get_defs function). This is probably due to a network timeout. However, if the problem persists please report to Heurist developers as it could indicate corruption of the database.');
                        success = false;
                    }

                    //console.log('ipage db struct  '+(new Date().getTime() / 1000 - _time_debug));
                    _time_debug = new Date().getTime() / 1000;


                    if(!window.hWin.HEURIST4.util.isnull(onPageInit) && $.isFunction(onPageInit)){
                        onPageInit(success);
                    }

                });
                return;
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
            success = false;
        }

        if($.isFunction(onPageInit)){
            onPageInit(success);
        }
    }

    //
    // it itakes name of theme from preferences , oherwise default theme is heurist
    //
    function applyTheme(){

        var prefs = window.hWin.HAPI4.get_prefs();
        if(!window.hWin.HR){
            //loads localization
            window.hWin.HR = window.hWin.HAPI4.setLocale(prefs['layout_language']);
        }

        /* unfortunately dynamic addition of theme and style is not applied properly.
        Browser takes some time on its parsing while we have already created some ui elements, need timeout.
        So, its better to detecct current theme on server side
        if(prefs['layout_theme'] && !(prefs['layout_theme']=="heurist" || prefs['layout_theme']=="base")){
        //load one of standard themes from jquery web resource
        cssLink = $('<link rel="stylesheet" type="text/css" href="http:......./themes/'+
        prefs['layout_theme']+'/jquery-ui.css" />');
        }else{
        //default BASE or HEURIST theme
        cssLink = $('<link rel="stylesheet" type="text/css" href="ext/jquery-ui-....../themes/'+prefs['layout_theme']+'/jquery-ui.css" />');
        }
        $("head").append(cssLink);
        $("head").append($('<link rel="stylesheet" type="text/css" href="h4styles.css?t='+(new Date().getTime())+'">'));
        */

        var layoutid = '<?=@$_REQUEST['ll']?>';
        if(window.hWin.HEURIST4.util.isempty(layoutid)){
            layoutid = window.hWin.HAPI4.get_prefs('layout_id');
            if(window.hWin.HEURIST4.util.isempty(layoutid)){
                layoutid = "H4Default";
            }
        }
        if(!window.hWin.HAPI4.sysinfo['layout']){
            window.hWin.HAPI4.sysinfo['layout'] = layoutid; //keep current layout

            if(layoutid=='DigitalHarlem' || layoutid=='DigitalHarlem1935'){ //digital harlem - @todo move style to layout
                $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/digital_harlem/dh_search_minimal.js').fail(function(){
                    window.hWin.HEURIST4.msg.showMsgErr('Cannot load script for DH search');
                });
                $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/digital_harlem/google_analytics.js').fail(function(){
                    window.hWin.HEURIST4.msg.showMsgErr('Cannot include Google Analtyics script');
                });
            }
        }



        //add version to title
        window.document.title = window.document.title+' V'+window.hWin.HAPI4.sysinfo.version;
    }

</script>

