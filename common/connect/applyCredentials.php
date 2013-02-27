<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

require_once(dirname(__FILE__)."/../config/initialise.php");

if (! defined('COOKIE_VERSION'))
	define('COOKIE_VERSION', 1);		// increment to force re-login when required


startMySession();

if (_is_logged_in()) {
/*****DEBUG****///error_log("in applyCred with valid login");
	if ((! defined('SAVE_URI'))  ||  strtoupper(SAVE_URI) != 'DISABLED') {
		if (defined('HEURIST_SESSION_DB_PREFIX')) {
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['last_uri'] = $_SERVER['REQUEST_URI'];
		}
	}
	// update cookie expiry time
	if (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['keepalive']) {
		$rv = setcookie('heurist-sessionid', session_id(), time() + 7*24*60*60, '/', HEURIST_SERVER_NAME);
	}
//	if (((! defined('REPLACE_DBNAME'))  ||  strtoupper(REPLACE_DBNAME) != 'DISABLED')&& defined("HEURIST_DBNAME")) {
//		$_SESSION['heurist_last_used_dbname'] = HEURIST_DBNAME ;
//	}

}
session_write_close();

function is_cookie_current_version() {
	return (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['cookie_version'] == COOKIE_VERSION);
}

function get_roles() {
	if (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'])
		return $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'];
	else
		return NULL;
}

function _is_logged_in() {
/*****DEBUG****///	error_log("in _is_logged_in instance prefix = ".HEURIST_SESSION_DB_PREFIX);
/*****DEBUG****///	error_log("in _is_logged_in restrict = ".defined('HEURIST_RESTRICT_GROUP_ID'));
	return (!!@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name']  &&
			(!defined('HEURIST_RESTRICT_GROUP_ID')  ||
				(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'][HEURIST_RESTRICT_GROUP_ID]  ||
				@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'][HEURIST_SYS_GROUP_ID]))  &&
			is_cookie_current_version());
}

if (!_is_logged_in()  &&  defined("BYPASS_LOGIN")) {
	// bypass all security!
	error_log("bypassing security");
	function get_user_id() { return 0; } // login in as guest
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	function get_group_ids() { return array(0); } //everyone is part of the AllUsersGroup
	function is_admin() { return false; }
	function is_logged_in() { return true; }
}else{
	function is_logged_in() {
		return _is_logged_in();
	}

	function is_admin($contx = 'database',$ug = 0) {
		if (!is_logged_in()) return false;
		switch ($contx) {
//			case 'sys':// TOD: remove is_admin('sys')
//				return @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'][HEURIST_SYS_GROUP_ID] == 'admin';
//				break;
			case 'group':
				if ($ug == 0 || $ug == get_user_id()) return true;
				if ($ug > 0)
					return @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'][$ug] == 'admin';
				return false;
				break;
			case 'database':
			default:
/*****DEBUG****///error_log("in is_admin username = ".@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name']);
/*****DEBUG****///error_log(">>>>is_admin=".@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'][HEURIST_OWNER_GROUP_ID]);
				return  (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'][HEURIST_OWNER_GROUP_ID]=="admin"); // ||
	//					@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name'] == 'stevewh';
		}
	}

	function get_user_id() {
		if (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_id']) {
			return $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_id'];
		}else if (!is_logged_in()){
			return 0;
		}
		return -1;
	}

	function get_group_ids() {
		if (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"]) {
			return array_keys($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"]);
		}
		else {
			return array();
		}
	}

	function get_user_name() { return @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_realname']; }
	function get_user_username() { return @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name']; }
}

function get_user_access() { // T1000.php only
	if (is_admin()) return 'admin';
	if (is_logged_in()) return 'member';
	return 'not-logged-in';
}

function get_access_levels() {//T1000.php only
	return array(
		'modeluser' => is_modeluser() ? 1 : 0,
		'admin' => is_admin() ? 1 : 0,
		'member'=> is_logged_in() ? 1 : 0
	);
}
//TODO : this doesn't match sysUGrp check validity
function is_modeluser() { return (get_user_username() == 'model_user'); }

function jump_sessions() {
	/* Some pages store a LOT of data in the session variables;
	 * this cripples the server whenever a page with session data is loaded, because PHP insists on parsing the session file.
	 * So,
         *        "Any problem in computer science can be solved with another layer of indirection.
	 *         But that usually will create another problem."
         *                                    --David Wheeler
	 *
	 * In the main session data, we store the ID of another session, which we use as persistent scratch space for
	 * (especially) the import functionality.  Call jump_sessions() to load this alternative session.
	 * Note that we haven't yet found out what the "another problem" is in this case.
	 */

	// variables to copy from the regular session to the alt
	$copy_vars = array('user_name', 'user_access', 'user_realname', 'user_id');

	$alt_sessionid = $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['alt-sessionid'];
	if (! $alt_sessionid) {
		session_start();
		$alt_sessionid = $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['alt-sessionid'] = sha1('import:' . rand());
		session_write_close();
	}

	$tmp = array();
	foreach ($copy_vars as $varname) $tmp[$varname] = $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$varname];

	session_id($alt_sessionid);
	session_start();

	foreach ($copy_vars as $varname) $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$varname] = $tmp[$varname];
}

/**
* used in login and after memebrship changes in admin/saveUsergrps
* @param mixed $user_id
*/
function reloadUserGroups($user_id){

		$res = mysql_query('select '.GROUPS_ID_FIELD.','.USER_GROUPS_ROLE_FIELD.' from '.USER_GROUPS_TABLE.','.GROUPS_TABLE.
							' where '.USER_GROUPS_GROUP_ID_FIELD.'='.GROUPS_ID_FIELD.
							' and '.USER_GROUPS_USER_ID_FIELD.'="'.$user_id.'"');
		while ($row = mysql_fetch_assoc($res)) {
			if ($row[USER_GROUPS_ROLE_FIELD])
				$groups[$row[GROUPS_ID_FIELD]] = $row[USER_GROUPS_ROLE_FIELD];
			else
				$groups[$row[GROUPS_ID_FIELD]] = 'member';
		}
		$groups[$user_id] = 'member';

		return $groups;
}

/**
*
*/
function startMySession(){

	if (@$_COOKIE['heurist-sessionid']) {
		session_id($_COOKIE['heurist-sessionid']);
	} else {
		session_id(sha1(rand()));
		setcookie('heurist-sessionid', session_id(), 0, '/', HEURIST_SERVER_NAME);
	}

	session_cache_limiter('none');
	session_start();
}

/**
* put your comment there...
*
*
* @param mixed $user_id
*/
function updateSessionForUser($user_id, $key, $value){

if(is_admin()){

	if(get_user_id()==$user_id){

		session_start();
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$key] = $value;

	}else{

		$path = realpath(session_save_path());
		$files = array_diff(scandir($path), array('.', '..'));
		foreach ($files as $file)
		{
			if(is_readable($path . '/' . $file)){
				try{
					$content = file_get_contents($path . '/' . $file);

					if(strlen($content)>0){
						session_id(substr($file,5));
						session_start();
						if (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_id'] == $user_id) {

							$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$key] = $value;
							$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$key.'_updated'] = 1;

							session_write_close();
    						break;
						}
						session_write_close();
					}
				}catch(Exception $e){
				}
			}
		}

		//back to my session
		startMySession();

	}
}

}

function isForAdminOnly($message="", $redirect=true)
{

    if ($redirect && !is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }


    if (is_admin()) {
        return false;
    }else{
?>
<html>
<head>
    <link rel=stylesheet href='../../common/css/global.css'>
</head>
<body>
    <div class=wrap>
        <div id=errorMsg>
            <span>You must be logged in as database owner <?=$message ?></span>
            <p>
                <a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a>
            </p>
        </div>
    </div>
</body>
</html>
<?php
        return true;
    }
}



?>