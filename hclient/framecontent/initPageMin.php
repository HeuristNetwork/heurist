<?php
/**
* Minimal initialization for page (without client side/HAPI)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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
if(!defined('PDIR')) define('PDIR','../../'); //need for js scripts

require_once(dirname(__FILE__)."/../../hsapi/System.php");

define('ERROR_REDIR', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');


$error_msg = '';
$isSystemInited = false;

// init main system class
$system = new System();

if(@$_REQUEST['db']){
    //if database is defined then connect to given database
    $isSystemInited = $system->init(@$_REQUEST['db']);
}
else{
    //db not defined
    $isSystemInited = $system->init(null, false);
}

if(!$isSystemInited){
    include ERROR_REDIR;
    exit();
}

$login_warning = 'To perform this action you must be logged in';
$invalid_access = true;

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
}else{
    $invalid_access = false;
}

// Check if current user has the necessary permissions
if(!$invalid_access && (defined('CREATE_RECORDS') || defined('DELETE_RECORDS'))){

    $required = '';
    $user_permissions = $system->getCurrentUser()['ugr_Status'];

    if(defined('CREATE_RECORDS') && !$user['add']){
        $required = 'create';
    }
    if(defined('DELETE_RECORDS') && !$user['delete']){
        $required .=  $required === '' ? 'delete' : ' and delete';
    }

    if($required !== ''){ $message = "To perform this action you need permission to $required records"; }
}

if(isset($message)){
    include ERROR_REDIR;
    exit();
}

function echo_flush($msg){
    //ob_start();
    print $msg;
    //ob_end_flush();
    //@ob_flush();
    //@flush();
}

//
// For script progress messages to web browser
//
function echo_flush2($msg){
    ob_start();
    print $msg;
    ob_end_flush();
    @ob_flush();
    @flush();
}
?>