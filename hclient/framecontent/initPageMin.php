<?php
/**
* initPageMin.php - Minimal initialization for page (without client side/HAPI)
* 
* Used for standalone admin utilities. Namely verification or export. 
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
use hserv\utilities\USanitize;

if(!defined('PDIR')) {define('PDIR','../../');}//need for js scripts

require_once dirname(__FILE__).'/../../autoload.php';

define('ERROR_REDIR', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');

$error_msg = '';
$isSystemInited = false;

// init main system class
$system = new hserv\System();

if(defined('ADMIN_PWD_REQUIRED') && ADMIN_PWD_REQUIRED==1){
    $sysadmin_pwd = USanitize::getAdminPwd();

    if($system->verifyActionPassword( $sysadmin_pwd, $passwordForServerFunctions) ){
        include_once dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
        exit;
    }
}

if(@$_REQUEST['db']){
    //if database is defined then connect to given database
    $isSystemInited = $system->init(@$_REQUEST['db']);
}
else{
    //db not defined
    $isSystemInited = $system->init(null, false);
}

if(!$isSystemInited){
    include_once ERROR_REDIR;
    exit;
}

$login_warning = 'To perform this action you must be logged in';
$invalid_access = true;

$is_admin = $system->isAdmin();

//
// to limit access to particular page
//
// @todo replacec with userCheckAccess
if(defined('LOGIN_REQUIRED') && !$system->hasAccess()){
    $message = $login_warning;
}elseif(defined('MANAGER_MEMBER_REQUIRED') && 
        !($system->isDbOwner() || $system->isMember([$system->settings->get('sys_OwnerGroupID')]))){
    $message = $login_warning.' as member of group \'Database Managers\'';     
}elseif(defined('MANAGER_REQUIRED') && !$is_admin ){ //A member should also be able to create and open database
    $message = $login_warning.' as Administrator of group \'Database Managers\'';
}elseif(defined('OWNER_REQUIRED') && !$system->isDbOwner()){
    $message = $login_warning.' as Database Owner';
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

    if($required !== ''){ $message = "To perform this action you need permission to $required records";}
}

if(isset($message)){
    include_once ERROR_REDIR;
    exit;
}

/**
 * Outputs a message.
 * Note: This function prints the message but does not explicitly flush the output buffer.
 * For flushing, see echo_flush2().
 *
 * @param string $msg The message to output.
 * @return void
 */
function echo_flush($msg){

    print $msg;



}

/**
 * Outputs a message and forces the output buffer to be sent to the browser.
 * Useful for sending progress messages during long-running scripts.
 *
 * @param string $msg The message to output and flush.
 * @return void
 */
function echo_flush2($msg){
    ob_start();
    print $msg;
    ob_end_flush();
    @ob_flush();
    @flush();
}
?>