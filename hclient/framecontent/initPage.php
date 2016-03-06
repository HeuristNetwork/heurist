<?php
/**
* Main header for all heurist pages.
* It
* 1) initializes System.php
* 2) prints out html header with minimum set of scripts
* 3) init client side hAPI
* 4) apply theme
* 5) load and init localoztion
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

$is_index_page = defined('PDIR');

if($is_index_page){
    //if PDIR is defined this script is main (root)
    define('ERROR_REDIR','hserver/utilities/list_databases.php');
}else{
    define('PDIR','../../');
    define('ERROR_REDIR','errorPage.php');
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

    if($is_index_page){
        require (ERROR_REDIR);
    }else{
        $err = $system->getError();
        $error_msg = @$err['message']?$err['message']:'';
        header('Location: '.ERROR_REDIR.'?msg='.rawurlencode($error_msg));
    }
    exit();
}

$user = $system->getCurrentUser();
$layout_theme = @$user['ugr_Preferences']['layout_theme'];
if(!$layout_theme) $layout_theme = 'heurist';

if($layout_theme=="heurist" || $layout_theme=="base"){
    //default BASE or HEURIST theme
    $cssLink = PDIR.'ext/jquery-ui-1.10.2/themes/'.$layout_theme.'/jquery-ui.css';
}else{
    //load one of standard themes from jquery web resource
    $cssLink = 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/'.$layout_theme.'/jquery-ui.css';
}

$log_warn = 'To perform this action you must be logged in';

function loginRequired(){
    global $log_warn, $system;
    if(!$system->is_logged_in()){
        header('Location: '.ERROR_REDIR.'?msg='.$log_warn);
        exit();
    }
}
function dbManagerRequired(){
    global $log_warn, $system;
    if(!$system->is_admin()){
        header('Location: '.ERROR_REDIR.'?msg='.$log_warn.' as Administrator of group \'Database Managers\'');
        exit();
    }
}
function dbOwnerRequired(){
    global $log_warn, $system;
    if(!$system->is_dbowner()){
        header('Location: '.ERROR_REDIR.'?msg='.$log_warn.' as Database Owner');
        exit();
    }
}

// base does not suit because some jquery widgets uses href (tabcontrol for example)
//        <base href="<?php echo PDIR;">

?>
<html>
    <head>
        <title><?=(@$_REQUEST['db']?$_REQUEST['db']:'').'. '.HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
        <meta content="telephone=no" name="format-detection">
        

        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>ext/jquery-file-upload/js/jquery.fileupload.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>ext/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
        
        <link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>" /> <!-- theme css -->
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />  <!-- base css -->

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>

        <!-- for debug  remark it and use getMultiScripts for production -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/search_minimal.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        
        <!-- loaded by demand script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/digital_harlem/dh_search_minimal.js"></script -->
        
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
    
    
        // if hAPI is not defined in parent(top most) window we have to create new instance
        $(document).ready(function() {
            // Standalone check
            if(!top.HAPI4){
                // In case of standalone page
                //load minimum set of required scripts
                $.getMultiScripts(['localization.js'/*, , 'utils_msg.js'
                                   'utils_ui.js', 'search_minimal.js', 'recordset.js', 'hapi.js'*/], '<?php echo PDIR;?>hclient/core/')
                .done(function() {
                    // all done
                    top.HAPI4 = new hAPI('<?php echo $_REQUEST['db']?>', onHapiInit);
                    
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

            
            if(!top.HEURIST4.rectypes){
                top.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        top.HEURIST4.rectypes = response.data.rectypes;
                        top.HEURIST4.terms = response.data.terms;
                        top.HEURIST4.detailtypes = response.data.detailtypes;
                    }else{
                        top.HEURIST4.msg.showMsgErr('Can not obtain database definitions, please consult Heurist developers');
                        success = false;
                    }

                    if($.isFunction(onPageInit)){
                        onPageInit(success);
                    }

                });
                return;
            }

        }else{
            top.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
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

        var prefs = top.HAPI4.get_prefs();
        if(!top.HR){
            //loads localization
            top.HR = top.HAPI4.setLocale(prefs['layout_language']);
        }

        /* unfortunately dynamic addition of theme and style is not applied properly.
        Browser takes some time on its parsing while we have already created some ui elements, need timeout.
        So, its better to detecct current theme on server side
        if(prefs['layout_theme'] && !(prefs['layout_theme']=="heurist" || prefs['layout_theme']=="base")){
        //load one of standard themes from jquery web resource
        cssLink = $('<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/'+
        prefs['layout_theme']+'/jquery-ui.css" />');
        }else{
        //default BASE or HEURIST theme
        cssLink = $('<link rel="stylesheet" type="text/css" href="ext/jquery-ui-1.10.2/themes/'+prefs['layout_theme']+'/jquery-ui.css" />');
        }
        $("head").append(cssLink);
        $("head").append($('<link rel="stylesheet" type="text/css" href="h4styles.css?t='+(new Date().getTime())+'">'));
        */

        var layoutid = '<?=@$_REQUEST['ll']?>';
        if(top.HEURIST4.util.isempty(layoutid)){
            layoutid = top.HAPI4.get_prefs('layout_id');
            if(top.HEURIST4.util.isempty(layoutid)){
                layoutid = "H4Default";
            }
        }
        if(!top.HAPI4.sysinfo['layout']){
            top.HAPI4.sysinfo['layout'] = layoutid; //keep current layout

            if(layoutid=='DigitalHarlem' || layoutid=='DigitalHarlem1935'){ //digital harlem - @todo move style to layout
            $.getScript(top.HAPI4.basePathV4+'hclient/widgets/digital_harlem/dh_search_minimal.js').fail(function(){
                top.HEURIST4.msg.showMsgErr('Cannot load script for DH search');
            });
            }
        }



        //add version to title
        window.document.title = window.document.title+' V'+top.HAPI4.sysinfo.version;
    }

</script>
