<?php

define('SAVE_URI', 'disabled');

require_once('cred.php');
require_once('db.php');
session_start();


if (! defined('BASE_PATH'))
	define('BASE_PATH', '');

define('USERS_DATABASE', 'ACLAdmin');
define('USERS_TABLE', 'sysUGrps');
define('USERS_ID_FIELD', 'ugr_ID');
define('USERS_USERNAME_FIELD', 'ugr_Name');
define('USERS_PASSWORD_FIELD', 'ugr_Password');
define('USERS_FIRSTNAME_FIELD', 'ugr_FirstName');
define('USERS_LASTNAME_FIELD', 'ugr_LastName');
define('USERS_ACTIVE_FIELD', 'ugr_Enabled');
define('GROUPS_TABLE', 'sysUGrps');
define('GROUPS_ID_FIELD', 'ugr_ID');
define('GROUPS_NAME_FIELD', 'ugr_Name');
define('USER_GROUPS_TABLE', 'sysUsrGrpLinks');
define('USER_GROUPS_USER_ID_FIELD', 'ugl_UserID');
define('USER_GROUPS_GROUP_ID_FIELD', 'ugl_GroupID');
define('USER_GROUPS_ROLE_FIELD', 'ugl_Role');


mysql_connection_localhost_select(USERS_DATABASE);


$LOGIN_ERROR = '';
if (@$_REQUEST['username']  or  @$_REQUEST['password']) {

	$res = mysql_query('select * from '.USERS_TABLE.' usr where usr.'.USERS_USERNAME_FIELD.' = "'.addslashes($_REQUEST['username']).'"');
    if ( ($user = mysql_fetch_assoc($res))  &&
		 $user[USERS_ACTIVE_FIELD] == 'Y'  &&
		 (crypt($_REQUEST['password'], $user[USERS_PASSWORD_FIELD]) == $user[USERS_PASSWORD_FIELD])) {

		$res = mysql_query('select grp.'.GROUPS_ID_FIELD.','.USER_GROUPS_ROLE_FIELD.' from '.USER_GROUPS_TABLE.','.GROUPS_TABLE.' grp'.
							' where '.USER_GROUPS_GROUP_ID_FIELD.'=grp.'.GROUPS_ID_FIELD.
							' and '.USER_GROUPS_USER_ID_FIELD.'="'.$user[USERS_ID_FIELD].'"');
		while ($row = mysql_fetch_assoc($res)) {
			if ($row[USER_GROUPS_ROLE_FIELD])
				$groups[$row[GROUPS_ID_FIELD]] = $row[USER_GROUPS_ROLE_FIELD];
			else
				$groups[$row[GROUPS_ID_FIELD]] = 'user';
		}

		$_SESSION['shsseri_cookie_version'] = COOKIE_VERSION;
		$_SESSION['shsseri_user_name'] = $user[USERS_USERNAME_FIELD];
		$_SESSION['shsseri_user_realname'] = $user[USERS_FIRSTNAME_FIELD] . ' ' . $user[USERS_LASTNAME_FIELD];
		$_SESSION['shsseri_user_id'] = $user[USERS_ID_FIELD];
		$_SESSION['shsseri_user_access'] = $groups;


		$expiry = '';
		if ($_REQUEST['session_type'] == 'public')
			setcookie('shsseri_sessionid', session_id(), ($expiry=0), '/', 'heuristscholar.org');
		else if ($_REQUEST['session_type'] == 'shared')
			setcookie('shsseri_sessionid', session_id(), ($expiry=time()+24*60*60), '/', 'heuristscholar.org');
		else if ($_REQUEST['session_type'] == 'remember')
			setcookie('shsseri_sessionid', session_id(), ($expiry=time()+365*24*60*60), '/', 'heuristscholar.org');

		/* bookkeeping */
		mysql_connection_localhost_overwrite(USERS_DATABASE);
		mysql_query('update sysUGrps usr set usr.ugr_LastLoginTime=now(), usr.ugr_LoginCount=usr.ugr_LoginCount+1
					  where usr.ugr_ID='.$user[USERS_ID_FIELD]);
		mysql_connection_db_overwrite(USERS_DATABASE);	// replication
		mysql_query('update sysUGrps usr set usr.ugr_LastLoginTime=now(), usr.ugr_LoginCount=usr.ugr_LoginCount+1
					  where usr.ugr_ID='.$user[USERS_ID_FIELD]);
		mysql_connection_localhost_select(USERS_DATABASE);

		header('Location: .');


	} else {
		$LOGIN_ERROR = 'Incorrect Username / Password';
	}

}


if (@$_REQUEST['logout']) {
	$_SESSION['shsseri_user_name'] = NULL;
	$_SESSION['shsseri_user_realname'] = NULL;
	$_SESSION['shsseri_user_id'] = NULL;
	$_SESSION['shsseri_user_access'] = NULL;
	$_SESSION['shsseri_user_roles'] = NULL;
	setcookie('favourites', '', time() - 3600);
	unset($_SESSION['shsseri_sessionid']);

	header('Location: login.php');

	return;
}


?>
<html>
 <head>
  <link rel="stylesheet" type="text/css" href="chesher.css">
  <style type="text/css">
  <!--
   h1 { font-size: 16px; margin-bottom: 20px;}
  -->
  </style>
 </head>

 <body onload="if (elt=document.getElementById('username')) elt.focus();">
 
  <div id=banner>
   <div class=heading>Review-o-matic</div>
  </div>

<div id="main" style="padding: 20px;">

<div class="heading" style="margin-bottom: 30px;">Login</div>
 
<form name="mainform" action="login.php" method="post">

<?php
	if (! is_logged_in()) {
?>

<table border="0" cellpadding="0" cellspacing="0"><tr>

<td style="vertical-align: top; padding-right: 80px;">

<h1>New User:</h1>
 <h3><a href="../admin/useradmin/add.php?register=1">Register</a></h3>

</td>

<td style="vertical-align: top;">

<?php
		if ($LOGIN_ERROR)
			echo "<p><font color=\"#FF0000\">".$LOGIN_ERROR."</font></p>";
?>
<h1>Existing User:</h1>
<h3>Login</h3>
  <table border="0" span class="normal">
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
</td>

</tr>

<tr><td colspan=2><hr></td></tr>
<tr>
 <td>
 </td>
 <td style="vertical-align: top;">
  <h3>Forgotten your password?</h3>
  <p><a href="../admin/useradmin/reset_password.php" onclick="window.open(this.href,'','status=0,scrollbars=0,width=300,height=200'); return false;">Click here to reset your password</a></p>
 </td>
</tr>
<tr><td colspan=2><hr></td></tr>
</table>

<?php
	} else {
?>
     
<p class="normalgr">You are currently logged-in as <b><tt><?= get_user_username() ?></tt></b></p>

<p><b><a href=".">Home</a></b>
&nbsp;&nbsp;
<input type="hidden" name="logout" value="1">
<b><a href="#" onclick="document.mainform.submit();">Log out</a></b></p>


<?php
}
?>



</form>

  </div>
 </body>
</html>
