<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

define('SAVE_URI', 'disabled');
require_once(dirname(__FILE__).'/applyCredentials.php');
require_once(dirname(__FILE__).'/../php/dbMySqlWrappers.php');
//require_once('applyCredentials.php');
//require_once('../php/dbMySqlWrappers.php');
session_start();
/*****DEBUG****///error_log("in login  loaded includes  userdb = ". USERS_DATABASE);
/*****DEBUG****///error_log(" params =". $_SERVER['QUERY_STRING']);

$last_uri = urldecode(@$_REQUEST['last_uri']);

/*****DEBUG****///error_log(" last uri = $last_uri");
//if (! $last_uri)
//	$last_uri = @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['last_uri'];
if (! $last_uri) {
	if (@$_SERVER['HTTP_REFERER']  &&  strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME']) === false) {
		$last_uri = $_SERVER['HTTP_REFERER'];
	}else if (defined('HEURIST_URL_BASE')) {
		$last_uri = HEURIST_URL_BASE;
	}
}

if (! defined('BASE_PATH')) {
	if (defined('HOST')) {
		define('BASE_PATH', 'http://'.HOST."/");	//default assume document directory is root for heurist
	}else{
		define('BASE_PATH', '');
	}
}

mysql_connection_db_select(USERS_DATABASE);


$LOGIN_ERROR = '';
if (@$_REQUEST['username']  or  @$_REQUEST['password']) {

	$res = mysql_query('select * from '.USERS_TABLE.' where '.USERS_USERNAME_FIELD.' = "'.addslashes($_REQUEST['username']).'"');
    if ( ($user = mysql_fetch_assoc($res))  &&
		 $user[USERS_ACTIVE_FIELD] == 'y'  &&
		 crypt($_REQUEST['password'], $user[USERS_PASSWORD_FIELD]) == $user[USERS_PASSWORD_FIELD] ) {
//		 (crypt($_REQUEST['password'], $user[USERS_PASSWORD_FIELD]) == $user[USERS_PASSWORD_FIELD]  ||  $_SESSION['heurist']['user_name'] == 'stevewh')) {
/*****DEBUG****///error_log("in login  after crypt check");

		$groups = reloadUserGroups($user[USERS_ID_FIELD]);

		$groups[$user[USERS_ID_FIELD]] = 'member'; // a person in a member of his own user type group, not admin as can't add users to this group

		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['cookie_version'] = COOKIE_VERSION;
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name'] = $user[USERS_USERNAME_FIELD];
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_realname'] = $user[USERS_FIRSTNAME_FIELD] . ' ' . $user[USERS_LASTNAME_FIELD];
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_id'] = $user[USERS_ID_FIELD];
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'] = $groups;


		$time = 0;
		if ($_REQUEST['session_type'] == 'public') {
			$time = 0;
		} else if ($_REQUEST['session_type'] == 'shared') {
			$time = time() + 24*60*60;
		} else if ($_REQUEST['session_type'] == 'remember') {
			$time = time() + 7*24*60*60;
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['keepalive'] = true;
		}
		setcookie('heurist-sessionid', session_id(), $time, '/', HOST_BASE);

		/* bookkeeping */
		mysql_connection_db_overwrite(USERS_DATABASE);
		mysql_query('update sysUGrps usr set usr.ugr_LastLoginTime=now(), usr.ugr_LoginCount=usr.ugr_LoginCount+1
					  where usr.ugr_ID='.$user[USERS_ID_FIELD]);

		mysql_connection_db_select(USERS_DATABASE);

		if ($last_uri){
			header('Location: ' . $last_uri);
		}


	} else {
		$LOGIN_ERROR = 'Incorrect Username / Password - try email address for user name';
	}

}


if (@$_REQUEST['logout']) {
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name']);
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_realname']);
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_id']);
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access']);
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results']);
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['sessionid']);
	setcookie('favourites', '', time() - 3600);

	header('Location: login.php?db='.HEURIST_DBNAME . ($last_uri ? '&last_uri=\'' . urlencode($last_uri).'\'' : ''));

	return;
}

?>
<html>
<head>
<title>Heurist Login</title>
<link rel=icon href='<?=HEURIST_SITE_PATH?>favicon.ico' type=image/x-icon>
<link rel="shortcut icon" href='<?=HEURIST_SITE_PATH?>favicon.ico' type=image/x-icon>

<link rel=stylesheet type=text/css href='<?=HEURIST_SITE_PATH?>common/css/global.css'>
<link rel=stylesheet type=text/css href='<?=HEURIST_SITE_PATH?>common/css/login.css'>



</head>

<body onload="if (elt=document.getElementById('username')) elt.focus();">
<script>
 if (window.parent != window) {
   <?php if (@$last_uri) { ?>
   top.location = "login.php?db=<?=HEURIST_DBNAME?>&last_uri=<?=$last_uri?>";
   <?php } else { ?>
   top.location = window.location;
   <?php } ?>
 }
</script>
<script src='../../common/js/utilsLoad.js'></script>
<script src='../../common/js/utilsUI.js'></script>


<div id=page style="padding: 20px;">
<a id=home-link href='../../'></a>
<div id=logo title="Click the logo at top left of any Heurist page to return to your Favourites"></div>

<div><h1>PLEASE LOGIN or <a href='../../' style='font-size:16px'>SEARCH</a></h1></div>

<form name=mainform method=post>

<div id="loginDiv">

<?php
	echo "<input type=hidden name=last_uri value={$last_uri}>\n";

	if (! is_logged_in()) {
?>

<?php
		if ($LOGIN_ERROR)
			echo "<p style=\"margin-left: 100px; color: red;\">".$LOGIN_ERROR."</p>";
?>
<table cellpadding=3 id="login-table">
	<tr class="input-row">
	<td class="input-header-cell">Database name</td>
	<td class="input-cell"><b><?php echo HEURIST_DBNAME; ?></b></td>
	</tr>

	<tr class="input-row">
	<td class="input-header-cell">Prefix</td>
	<td class="input-cell"><?php echo HEURIST_DB_PREFIX; ?></td>
	</tr>

	<tr class="input-row">
	<td class="input-header-cell">Username</td>
	<td class="input-cell"><input type="text" name="username" id="username" size="20" class="in"><br/>email address by default</td>
	</tr>

   <tr class="input-row">
    <td class="input-header-cell">Password</td>
    <td class="input-cell"><input type="password" name="password" size="20" class="in"><br/>case sensitive</td>
   </tr>

   <tr class="input-row">
    <td class="input-header-cell"></td>
    <td class="input-cell"></td>
   </tr>


   <tr class="input-row">
    <td class="input-header-cell"></td>
    <td class="input-cell"><input type="radio" name="session_type" value="public">Expire on browser close (public computer)<br>
        <input type="radio" name="session_type" value="shared">Expire on user logout (shared computer)<br>
        <input type="radio" name="session_type" value="remember" checked>Remember me on this computer (your computer)</td>
   </tr>

   <tr><td colspan="2"></td></tr>

   <tr>
    <td></td>
    <td><input type="submit" value="  Login  " >&nbsp;&nbsp;&nbsp;</td>
   </tr>

</table>

 <p align=center>
  Forgotten your password?
  &nbsp;
  <a href='<?=HEURIST_URL_BASE?>admin/ugrps/resetUserPassword.php?db=<?=HEURIST_DBNAME?>' onclick="window.open(this.href,'','status=0,scrollbars=0,width=500,height=200'); return false;">Click here to reset your password</a>
 </p>



<?php
	} else {
?>


<table id=login_table>
<tr><td>
<p>You are currently logged-in as <b><?= get_user_name() ?> (<?= get_user_username() ?>)</b></p>

<p><b><a href="<?=HEURIST_URL_BASE?>">Heurist home</a></b></p>

<?php
		if ($last_uri)
			echo "<p><b><a href=\"{$last_uri}\">Return to application</a></b></p>\n\n";
?>

<p><b><a href='login.php?db=<?=HEURIST_DBNAME?>&logout=1'>Log out</a></b></p>

<br>

<p>
If you did not specifically request the login page, you may be seeing this page<br>
because you don't have sufficient access level for the function requested,<br>
or do not belong to the group of users who own it.
</p>
<p>
Please log in as an administrator or contact
<a href="mailto:info@heuristscholar.org">Ian Johnson</a> to request a higher<br>
level of access or membership of the group of owners of this function.
</p>
</td></tr>
</table>

<?php
}
?>

</div>

</form>

  </div>
 </body>
</html>
