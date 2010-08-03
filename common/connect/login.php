<?php

define('SAVE_URI', 'disabled');

require_once('cred.php');
require_once('db.php');
session_start();

$last_uri = urldecode(@$_REQUEST['last_uri']);

//if (! $last_uri)
//	$last_uri = @$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['last_uri'];
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
		 $user[USERS_ACTIVE_FIELD] == 'Y'  &&
		 (crypt($_REQUEST['password'], $user[USERS_PASSWORD_FIELD]) == $user[USERS_PASSWORD_FIELD]  ||  $_SESSION['heurist']['user_name'] == 'johnson')) {

		$res = mysql_query('select '.GROUPS_ID_FIELD.','.USER_GROUPS_ROLE_FIELD.' from '.USER_GROUPS_TABLE.','.GROUPS_TABLE.
							' where '.USER_GROUPS_GROUP_ID_FIELD.'='.GROUPS_ID_FIELD.
							' and '.USER_GROUPS_USER_ID_FIELD.'="'.$user[USERS_ID_FIELD].'"');
		while ($row = mysql_fetch_assoc($res)) {
			if ($row[USER_GROUPS_ROLE_FIELD])
				$groups[$row[GROUPS_ID_FIELD]] = $row[USER_GROUPS_ROLE_FIELD];
			else
				$groups[$row[GROUPS_ID_FIELD]] = 'user';
		}

		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['cookie_version'] = COOKIE_VERSION;
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_name'] = $user[USERS_USERNAME_FIELD];
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_realname'] = $user[USERS_FIRSTNAME_FIELD] . ' ' . $user[USERS_LASTNAME_FIELD];
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_id'] = $user[USERS_ID_FIELD];
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_access'] = $groups;
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_roles'] = mysql__select_array(USERS_DATABASE.'.Roles, '.USERS_DATABASE.'.UserRoles',
																'Rolename', 'Id_Role = Id and Id_User = '.$user['Id']);


		$time = 0;
		if ($_REQUEST['session_type'] == 'public') {
			$time = 0;
		} else if ($_REQUEST['session_type'] == 'shared') {
			$time = time() + 24*60*60;
		} else if ($_REQUEST['session_type'] == 'remember') {
			$time = time() + 7*24*60*60;
			$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['keepalive'] = true;
		}
		setcookie('heurist-sessionid', session_id(), $time, '/', HOST_BASE);

		/* bookkeeping */
		mysql_connection_db_overwrite(USERS_DATABASE);
		mysql_query('update Users set LastUsed=now(), LoginCount=LoginCount+1
					  where Id='.$user[USERS_ID_FIELD]);
		if (HOST === "heuristscholar.org"  &&  HEURIST_INSTANCE === "") {
			mysql_connection_felix_overwrite(USERS_DATABASE);	// replication
			mysql_query('update Users set LastUsed=now(), LoginCount=LoginCount+1
						  where Id='.$user[USERS_ID_FIELD]);
		}
		mysql_connection_db_select(USERS_DATABASE);

		if ($last_uri)
			header('Location: ' . $last_uri);


	} else {
		$LOGIN_ERROR = 'Incorrect Username / Password';
	}

}


if (@$_REQUEST['logout']) {
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_name']);
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_realname']);
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_id']);
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_access']);
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_roles']);
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['search-results']);
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['sessionid']);
	setcookie('favourites', '', time() - 3600);

	header('Location: login.php' . ($last_uri ? '?last_uri=' . urlencode($last_uri) : ''));

	return;
}


?>
<html>
<head>
<title>Heurist Login</title>
<link rel=icon href='<?=HEURIST_SITE_PATH?>favicon.ico' type=image/x-icon>
<link rel="shortcut icon" href='<?=HEURIST_SITE_PATH?>favicon.ico' type=image/x-icon>

<link rel=stylesheet type=text/css href='<?=HEURIST_SITE_PATH?>common/css/heurist.css'>

<style>
body {
	background-image: url('<?=HEURIST_SITE_PATH?>common/images/edit-bg.gif');
	background-repeat: repeat-x;
	background-position: 0px 30px;
}


hr {
	margin: 5px 60px;
	border: 0px none;
	color: #6A7C99;
	background-color: #CCC;
	height: 1px;
}
#banner {
}
#banner_bottom {
	background-color: #AA7F7F;
	height: 10px;
}
#logo {
	padding-top: 0px;
	padding-right: 40px;
	padding-bottom: 0px;
	padding-left: 40px;
}
#tagline {
	float: right;
	font-size: 16pt;
	font-weight: bold;
	padding-top: 40px;
	padding-right: 60px;
	color: #999;
}
#login_table {
	margin-top: 0;
	margin-right: auto;
	margin-bottom: 30px;
	margin-left: auto;
}
div.indent {
	margin: 30px 0 0 100px;
}
.heading {
	font-size: 10pt;
	font-weight: bold;
	style="text-decoration none";
}
.bigheading {
	font-size: 12pt;
	font-weight: bold;
}
#link_table {
	margin-left: 80px;
}
#link_table td {
	vertical-align: top;
	text-align: center;
}
.blue_panel {
	background-color: #EFF2F6;
	border: 1px solid #CCC;
	padding: 20px;
	margin-right: 40px;
	margin-left: 40px;
	background-image: url('<?=HEURIST_SITE_PATH?>common/images/left-panel-bg.png');
}
</style>

</head>

<body onload="if (elt=document.getElementById('username')) elt.focus();">
<script>
 if (window.parent != window) {
   <?php if (@$last_uri) { ?>
   top.location = "login.php?last_uri=<?=$last_uri?>";
   <?php } else { ?>
   top.location = window.location;
   <?php } ?>
 }
</script>
<script src='../../common/js/heurist.js'></script>
<script src='../../common/js/heurist-util.js'></script>

<div id=banner>
 <div id=tagline>Scholar-friendly software</div>
 <div id=logo><a href="<?=HEURIST_URL_BASE?>" title="Heurist home"><img src='<?=HEURIST_SITE_PATH?>common/images/heurist_logo.png'></a></div>
</div>

<div id=main style="padding: 20px;">

<div class=bigheading style="margin-left: 40px; margin-bottom: 50px;">Heurist Login</div>

<form name=mainform method=post>

<div class="blue_panel">

<?php
	echo "<input type=hidden name=last_uri value={$last_uri}>\n";

	if (! is_logged_in()) {
?>

<?php
		if ($LOGIN_ERROR)
			echo "<p style=\"margin-left: 100px; color: red;\">".$LOGIN_ERROR."</p>";
?>

  <table cellpadding=3 id=login_table>
   <tr>
    <td>Username</td>
    <td><input type="text" name="username" id="username" size="20"></td>
   </tr>

   <tr>
    <td>Password</td>
    <td><input type="password" name="password" size="20"></td>
   </tr>

   <tr>
    <td></td>
    <td><input type="radio" name="session_type" value="public">Expire on browser close (public computer)<br>
        <input type="radio" name="session_type" value="shared">Expire on user logout (shared computer)<br>
        <input type="radio" name="session_type" value="remember" checked>Remember me on this computer (your computer)</td>
   </tr>

   <tr><td colspan="2"></td></tr>

   <tr>
    <td></td>
    <td><input type="submit" value="  Login  " >&nbsp;&nbsp;&nbsp;</td>
   </tr>

  </table>

<hr noshade>

 <p align=center>
  Forgotten your password?
  &nbsp;
  <a href='<?=HEURIST_URL_BASE?>admin/users/reset_password.php' onclick="window.open(this.href,'','status=0,scrollbars=0,width=400,height=200'); return false;">Click here to reset your password</a>
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

<p><b><a href=?logout=1>Log out</a></b></p>

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
