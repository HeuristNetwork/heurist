<?php

/**
* Error page. Either system cannot be inited (db connecction, failure with configuration),
* or wrong set of parameters has been provided.
*
* Notes:
* 1) for main page, the redirection goes to list_databases.php that allows to select different database
* 2) for non-page requests (api calls) error is returned in json
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__)."/../../hserver/System.php");


// init main system class
$system = new System();
$layout_theme = null;
$isSystemInited = $system->init(@$_REQUEST['db'], true);

if($isSystemInited){
    $user = $system->getCurrentUser();
    $layout_theme = @$user['ugr_Preferences']['layout_theme'];
    $error_msg = @$_REQUEST['msg'];
    if(!$error_msg){
        $error_msg = 'Unknown error.';
    }
}else{
    //cannot connect to given database
    $err = $system->getError();
    $error_msg = @$err['message'];
    if(!$error_msg){
        $error_msg = 'Unknown error. Cannot init Heurist system';
    }
}


if(!$layout_theme) $layout_theme = 'heurist';

if($layout_theme=="heurist" || $layout_theme=="base"){
    //default BASE or HEURIST theme
    $cssLink = '../../ext/jquery-ui-themes-1.12.1/themes/'.$layout_theme.'/jquery-ui.css';
}else{
    //load one of standard themes from jquery web resource
    $cssLink = 'https://code.jquery.com/ui/1.12.1/themes/'.$layout_theme.'/jquery-ui.css';
}
?>
<html>
    <head>
        <title><?=HEURIST_TITLE?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel=icon href="../../favicon.ico" type="image/x-icon">

        <link rel="stylesheet" href="<?php echo $cssLink;?>" />
        <link rel="stylesheet" type="text/css" href="../../h4styles.css">
    </head>
    <body style="padding:44px;" class="ui-heurist-header1">
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

            <div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
                <?php echo $error_msg;?>
            </div>
        </div>
    </body>
</html>
