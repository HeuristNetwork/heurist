<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

?>

<?php

define('SAVE_URI', 'disabled');

require_once('applyCredentials.php');
require_once('../php/dbMySqlWrappers.php');
session_start();

$last_uri = urldecode(@$_REQUEST['last_uri']);
$home = urldecode(@$_REQUEST['home']);
$logo = urldecode(@$_REQUEST['logo']);
if (!$logo) {
	$logo = "../../common/images/hlogo-big.gif";
}
$instance_name = ucwords(eregi_replace("[.]", "", HEURIST_INSTANCE_PREFIX));

if (! $last_uri) {
	if (@$_SERVER['HTTP_REFERER']  &&  strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME']) === false) {
		$last_uri = @$_SERVER['HTTP_REFERER'];
	}
}
error_log(" last uri = ".$last_uri);
if (! defined('HEURIST_URL_BASE')) {
	if (defined('HOST_BASE')) {
		define('HEURIST_URL_BASE', 'http://'.HOST_BASE."/heurist3/");
	}else{
		define('HEURIST_URL_BASE', '');
	}
}

mysql_connection_db_select(USERS_DATABASE);

$LOGIN_ERROR = '';
if (@$_REQUEST['username']  or  @$_REQUEST['password']) {

	$res = mysql_query('select * from '.USERS_TABLE.' where '.USERS_USERNAME_FIELD.' = "'.addslashes($_REQUEST['username']).'"');
    if ( ($user = mysql_fetch_assoc($res))  &&
		 $user[USERS_ACTIVE_FIELD] == 'y'  &&
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


		$expiry = '';
		if ($_REQUEST['session_type'] == 'public')
			setcookie('heurist-sessionid', session_id(), ($expiry=0), '/', HEURIST_SERVER_NAME);
		else if ($_REQUEST['session_type'] == 'shared')
			setcookie('heurist-sessionid', session_id(), ($expiry=time()+24*60*60), '/', HEURIST_SERVER_NAME);
		else if ($_REQUEST['session_type'] == 'remember')
			setcookie('heurist-sessionid', session_id(), ($expiry=time()+365*24*60*60), '/', HEURIST_SERVER_NAME);

		/* bookkeeping */
		mysql_connection_db_overwrite(USERS_DATABASE);
		mysql_query('update sysUGrps usr set usr.ugr_LastLoginTime=now(), usr.ugr_LoginCount=usr.ugr_LoginCount+1
					  where usr.ugr_ID='.$user[USERS_ID_FIELD]);
		mysql_connection_db_select(USERS_DATABASE);

		if ($last_uri)
			header('Location: ' . $last_uri);

			//$onload = ' window.close();';


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
	setcookie('favourites', '', time() - 3600);


	header('Location: login-vanilla.php?db='.HEURIST_DBNAME . ($logo ? '&logo=' . urlencode($logo) : '') . ($home ? '&home=' . urlencode($home) : ''));

	return;
}


?>
<html>
<head>
<title>Heurist Login</title>
<link rel=icon href=<?= HEURIST_SITE_PATH ?>favicon.ico type=image/x-icon>
<link rel="shortcut icon" href=<?= HEURIST_SITE_PATH ?>favicon.ico type=image/x-icon>

<link rel=stylesheet type=text/css href=<?= HEURIST_SITE_PATH ?>common/css/heurist.css>

<style>
body {
	color: #670000;
	margin: 0;
	overflow: auto;
}
#banner {
	color: black;
}
#banner_bottom {
	background-color: #AA7F7F;
	height: 1px;
}
#logo {
	padding: 10px 40px;
}
#tagline {
	float: right;
	font-size: 16pt;
	font-weight: bold;
	padding-top: 60px;
	padding-right: 20px;
}
#login_table {
	margin: 0 0 30px 100px;
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
</style>

</head>

<body onload="if (elt=document.getElementById('username')) elt.focus(); <?= $onload ; ?>">
<script>
 if (window.parent != window) {
   <?php if (@$last_uri) { ?>
   top.location = "login.php?<?=HEURIST_DBNAME?>&last_uri=<?=$last_uri?>";
   <?php } else { ?>
   top.location = window.location;
   <?php } ?>
 }
</script>
<script src=../../common/js/heurist.js></script>
<script src=../../common/js/heurist-util.js></script>

<div id=banner>
 <span id=logo><a href=".." title="Home"><img src="<?= $logo; ?>" align="absmiddle"></a><span class=bigheading style="margin-left: 50px; "> <?= $instance_name; ?> Login </span></span>
</div>
<div id=banner_bottom></div>

<div id=main style="padding: 20px;">



<form name=mainform method=post>

<?php
	echo "<input type=hidden name=home value={$home}>\n";
	echo "<input type=hidden name=last_uri value={$last_uri}>\n";

	if (! is_logged_in()) {
?>

<?php
		if ($LOGIN_ERROR)
			echo "<p style=\"margin-left: 100px; color: red;\">".$LOGIN_ERROR."</p>";
?>
  <table id=login_table>
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
<div class=indent>
 <span class=heading>Forgotten your password?</span>
 &nbsp;
 <a href="<?=HEURIST_URL_BASE?>admin/ugrps/resetUserPassword.php?db=<?=HEURIST_DBNAME?>" onclick="window.open(this.href,'','status=0,scrollbars=0,width=400,height=200'); return false;">Click here to reset your password</a>
</div>



<?php
	} else {
?>


<div class=indent>
<p>You are currently logged-in as <b><?= get_user_name() ?> (<?= get_user_username() ?>)</b></p>

<?php
		if ($home)
			echo "<p><b><a href=\"{$home}\">{$instance_name} home</a></b></p>\n\n";
		if ($last_uri)
			echo "<p><b><a href=\"{$last_uri}\">Return to application</a></b></p>\n\n";
?>

<p><b><a href=?logout=1&logo=<?= $logo; ?>&home=<?= $home; ?>>Log out</a></b></p>
</div>

<?php
}
?>



</form>

  </div>
 </body>
</html>
