<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

?>
<html>
 <head>
  <title>Heurist Password Reset</title>
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
 </head>
 <body>
 <div style="padding: 10px;">
 <h3>Reset lost/forgotten password</h3>
<?php

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

function email_user ($user_id, $firstname, $email, $passwd, $username) {
	$msg =
'Dear '.$firstname.',

Your Heurist password has been reset.

Your username is: '.$username.'
Your new password is: '.$passwd.'

To change your password, go to:
'.HEURIST_URL_BASE.'admin/ugrps/editUser.php?db='.HEURIST_DBNAME.'&Id='.$user_id.'

You will first be asked to log in with the new password above.
';
	mail($email, 'Heurist password reset', $msg, 'From: '.HEURIST_MAIL_TO_INFO);
}


if (@$_REQUEST['username']) {
	mysql_connection_overwrite(USERS_DATABASE);

	$username = addslashes($_REQUEST['username']);

	$res = mysql_query('select ugr_ID,ugr_eMail,ugr_FirstName,ugr_Name from sysUGrps usr where usr.ugr_Name = "'.$username.'" or ugr_eMail = "'.$username.'"');
	$row = mysql_fetch_assoc($res);
	$username = $row['ugr_Name'];
	$user_id = $row['ugr_ID'];
	$email = $row['ugr_eMail'];
	$firstname = $row['ugr_FirstName'];

	if ($user_id) {
		$new_passwd = generate_passwd();
		mysql_query('update sysUGrps usr set ugr_Password = "'.hash_it($new_passwd).'" where ugr_ID = ' . $user_id);

		email_user($user_id, $firstname, $email, $new_passwd, $username);

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
 <input type="text" name="username" size="20"> &nbsp;&nbsp;
 <input type="submit" value="reset password">
</form>
<?php
}
?>
 </div>
 </body>
</html>
