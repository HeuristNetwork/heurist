<?php
/* Filename: edit_users.php
*  Template File: edit_users.html
*  Rederer File: edit_users.php
*  General Function Purpose: Allow administrators to edit the values in user lists.
*  File Function: Render page.
*/


define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');


if ( ! (is_logged_in()  &&
         (is_admin()  ||  $_REQUEST['Id'] == get_user_id()) )) {
        header('Location: '.HEURIST_URL_BASE.'common/connect/login.php');
        return;
}

mysql_connection_overwrite(USERS_DATABASE);
$template = file_get_contents('edit.html');

$lexer = new Lexer($template);
$body = new BodyScope($lexer);

$body->global_vars['edit-success'] = 0;
$body->global_vars['password-not-changed'] = 0;
$body->global_vars['approve'] = 0;

if (@$_REQUEST['approve']  &&  is_admin())
	$body->global_vars['approve'] = 1;

$body->global_vars['proj-group-link'] = HEURIST_INSTANCE === '' ? ' | <a href="projectgroupadmin.php">Edit project groups</a>' : '';

$body->verify();
if (@$_REQUEST['_submit']) {
	$usr_id = intval(@$_REQUEST['Id']);

	if (@$_REQUEST['user_update_Password']) {
		if ($_REQUEST['user_update_Password'] != @$_REQUEST['password2']) {
			$_REQUEST['user_update_Password'] = '';
			$body->global_vars['password-not-changed'] = 1;
		}
		else {
			$s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
			$salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
			$_REQUEST['user_update_Password'] = crypt($_REQUEST['user_update_Password'], $salt);
		}
	}

	$username = $_REQUEST['user_update_Username'];
	$email = $_REQUEST['user_update_EMail'];

	$body->input_check();
	if ($body->satisfied) {
		$body->execute();

		if (@$_REQUEST['Active'])
			mysql_query("update Users set Active='Y' where Id=$usr_id");
		else
			mysql_query("update Users set Active='N' where Id=$usr_id");

		$body->global_vars['edit-success'] = 1;

		$res = mysql_query('select * from Users where Id = '.$usr_id);
		$row = mysql_fetch_assoc($res);

		if (@$_REQUEST['approved']) {
			$email_text =
"Your Heurist account registration has been approved.

Login at:

".HEURIST_URL_BASE."

with the username: " . $row['Username'] . ".

We recommend visiting the 'Take the Tour' section and
also visiting the Help function, which provides comprehensive
overviews and step-by-step instructions for using Heurist.

";
			error_log("sending user confirmation mail: " . $email . ", " . $row['Realname'].' ['.$row['EMail'].']');
			$rv = mail($email, 'Heurist User Registration: '.$row['Realname'].' ['.$row['EMail'].']', $email_text, "From: info@acl.arts.usyd.edu.au\r\nCc: info@acl.arts.usyd.edu.au");
			if (! $rv) error_log("mail send failed: " . $email . ", " . $row['Realname'].' ['.$row['EMail'].']');
		}

	}
}
$body->render();
?>
