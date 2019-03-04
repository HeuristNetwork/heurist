<?php
/**
* Minimal initialization for page
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

require_once(dirname(__FILE__)."/../../hsapi/System.php");

if(!defined('PDIR')) define('PDIR','../../'); //need for js scripts

define('ERROR_REDIR', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');

$error_msg = '';
$isSystemInited = false;

// init main system class
$system = new System();

if(@$_REQUEST['db']){
    //if database is defined then connect to given database
    $isSystemInited = $system->init(@$_REQUEST['db']);
}

if(!$isSystemInited){
    include ERROR_REDIR;
    exit();
}

$login_warning = 'To perform this action you must be logged in';

//
// to limit access to particular page
// define const in the very begining of your php code  just before require_once(dirname(__FILE__)."/initPage.php");
//
if(defined('LOGIN_REQUIRED') && !$system->has_access()){
    $message = $login_warning;
}else if(defined('MANAGER_REQUIRED') && !$system->is_admin() ){ //A member should also be able to create and open database
    $message = $login_warning.' as Administrator of group \'Database Managers\'';
}else if(defined('OWNER_REQUIRED') && !$system->is_dbowner()){
    $message = $login_warning.' as Database Owner';
}
if(isset($message)){
    include ERROR_REDIR;
    exit();
}
//$system->defineConstants(); //init constants for record and field types

$user = $system->getCurrentUser();
$layout_theme = @$user['ugr_Preferences']['layout_theme'];
if(!$layout_theme) $layout_theme = 'heurist';

if($layout_theme=="heurist" || $layout_theme=="base"){
    //default BASE or HEURIST theme
    $cssLink = PDIR.'external/jquery-ui-themes-1.12.1/themes/'.$layout_theme.'/jquery-ui.css';
}else{
    //load one of standard themes from jquery web resource
    $cssLink = 'https://code.jquery.com/ui/1.12.1/themes/'.$layout_theme.'/jquery-ui.css';
}

function echo_flush($msg){
    ob_start();
    print $msg;
    @ob_flush();
    @flush();
}
?>