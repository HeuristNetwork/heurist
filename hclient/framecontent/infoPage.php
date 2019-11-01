<?php

/**
* Information page
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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
$is_inlcuded = false;

if(!defined('PDIR')) {
    $is_inlcuded = true;
    define('PDIR','../../');   
    require_once(dirname(__FILE__)."/../../hsapi/System.php");
}

//variable is_error can be defined as global
if(!isset($is_error)){
    $is_error = true;
}

//variable message can be defined as global
if(!isset($message)){
    if( @$_REQUEST['error'] ){
        $message = $_REQUEST['error'];
    }else if( @$_REQUEST['message'] ){
        $message = $_REQUEST['message'];
        $is_error = false;
    }else{ 
        //take error message from system
        if(isset($system)){
            $err = $system->getError();
            $message = @$err['message'];
        }
        
    }
    if(!$message){
        $message ='Unknown error.';
    }
}
?>
<html>
    <head>
        <title><?php print defined('HEURIST_TITLE')?HEURIST_TITLE:"Heurist"; ?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <!-- CSS -->
        <?php include PDIR.'hclient/framecontent/initPageCss.php'; ?>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
    </head>
    <body style="padding:44px;" class="ui-heurist-header1">
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

            <div class="<?php echo ($is_error)?'ui-state-error':''; ?>" 
                style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon <?php echo ($is_error)?'ui-icon-alert':'ui-icon-info'; ?>" 
                      style="float: left; margin-right:.3em;font-weight:bold"></span>
                <?php echo $message;?>
            </div>
        </div>
    </body>
</html>
