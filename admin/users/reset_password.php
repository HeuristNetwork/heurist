<html>
 <head>
  <title>ACL/SHSSERI Password Reset</title>
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>css/newshsseri.css">
 </head>
 <body>
 <div style="padding: 10px;">
 <h3>Reset lost/forgotten password</h3>
<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

function generate_passwd ($length = 8) {
	$passwd = '';
	$possible = '023456789bcdfghjkmnpqrstvwxyz';
	while (strlen($passwd) < $length) {
		$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
		if (!strstr($passwd, $char)) $passwd .= $char;
	}
	return $passwd;
}

function hash_it ($passwd) {
	$s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
	$salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
	return crypt($passwd, $salt);
}

function email_user ($user_id, $firstname, $email, $passwd) {
	$msg =
'Dear '.$firstname.',

Your Heurist password has been reset.

Your new password is: '.$passwd.'

To change your password, go to:
'.HEURIST_URL_BASE.'admin/users/edit.php?Id='.$user_id.'

(you will first be asked to log in with the new password above)
';
	mail($email, 'Heurist password reset', $msg, 'From: info@heuristscholar.org');
}


if (@$_REQUEST['username']) {
	mysql_connection_overwrite(USERS_DATABASE);

	$username = addslashes($_REQUEST['username']);

	$res = mysql_query('select Id,EMail,firstname from Users where Username = "'.$username.'" or EMail = "'.$username.'"');
	$row = mysql_fetch_assoc($res);
	$user_id = $row['Id'];
	$email = $row['EMail'];
	$firstname = $row['firstname'];

	if ($user_id) {
		$new_passwd = generate_passwd();
		mysql_query('update Users set Password = "'.hash_it($new_passwd).'" where Id = ' . $user_id);

		email_user($user_id, $firstname, $email, $new_passwd);

		print '<p>Your password has been reset.  You should receive an email shortly with your new password.</p>'."\n";

	} else {
		$error = '<p style="color: red;">Username does not exist</p>'."\n";
	}

}

if (!$_REQUEST['username']  ||  $error) {
?>
<p>Enter your username OR email address below and a new password will be emailed to you.</p>
<?= $error ?>
<form method="get">
 Enter username / email:
 <input type="text" name="username" size="20">
 <br>
 <br>
 <input type="submit" value="reset password">
</form>
<?php
}
?>
 </div>
 </body>
</html>
