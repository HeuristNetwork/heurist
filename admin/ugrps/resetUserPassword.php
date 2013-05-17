<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* resetUserPassword.php
* is called from login to reset forgotten password.
* Generates new password and send it to user email
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


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
"Dear ".$firstname.",

Your Heurist password has been reset.

Your username is: ".$username."
Your new password is: ".$passwd."

To change your password go to My Profile -> My User Info in the top right menu

You will first be asked to log in with the new password above.
";
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
 <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
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
