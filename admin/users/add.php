<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');


if (!@$_REQUEST['register'] && !(is_logged_in() && is_admin())) {
	header('Location: '.HEURIST_URL_BASE.'common/connect/login.php');
	return;
}

$recaptcha_public_key = "6LdOkgQAAAAAAJA5_pdkrwcKA-VFPPdihgoLiWmT";
$recaptcha_private_key = "6LdOkgQAAAAAALRx8NbUn9HL50ykaTjf1dv5G3oq";

mysql_connection_overwrite(DATABASE);
$template = file_get_contents('add.html');

$lexer = new Lexer($template);

$body = new BodyScope($lexer);

$body->global_vars['recaptcha'] = "";

if (@$_REQUEST['register']) {
	$body->global_vars['register'] = 1;
	require_once(dirname(__FILE__)."/../../external/recaptcha/recaptchalib.php");
	$body->global_vars['recaptcha'] = recaptcha_get_html($recaptcha_public_key);
} else {
	$body->global_vars['register'] = 0;
}

$body->global_vars['new-user-id'] = 0;


$body->global_vars['model-user-dropdown'] = '<select name="model_usr_id">'."\n";
$res = mysql_query("select Id, concat(firstname,' ',lastname) as realname from ".USERS_DATABASE.".Users where IsModelUser = 1" . (@$_REQUEST['register'] ? ' limit 1' : ''));
while ($row = mysql_fetch_assoc($res)) {
	$body->global_vars['model-user-dropdown'] .= ' <option value="'.$row['Id'].'">'.$row['realname'].'</option>'."\n";
}
$body->global_vars['model-user-dropdown'] .= '</select>'."\n";
if (@$_REQUEST['register']) {
	$body->global_vars['model-user-dropdown'] = '<input type="hidden" name="model_usr_id" value="96">'."\n";
}

$body->global_vars['proj-group-link'] = HEURIST_INSTANCE === '' ? ' | <a href="'.HEURIST_URL_BASE.'admin/users/projectgroupadmin.php">Edit project groups</a>' : '';

$body->verify();


$dup_check_ok = true;
if (@$_REQUEST['_submit']) {

	// check for duplicate fields
	$res = mysql_query('select Id from '.USERS_DATABASE.'.Users where Username = "'.$_REQUEST['user_insert_Username'].'"');
	if (mysql_num_rows($res) > 0) {
		$body->global_vars['-ERRORS'][] = 'The Username you have chosen is already in use.  Please choose another.';
		$dup_check_ok = false;
	}
	$res = mysql_query('select Id from '.USERS_DATABASE.'.Users where EMail = "'.$_REQUEST['user_insert_EMail'].'"');
	if (mysql_num_rows($res) > 0) {
		$body->global_vars['-ERRORS'][] = 'The EMail address you have entered is already registered.  Please use another.';
		$dup_check_ok = false;
	}

	if ($_REQUEST['register']) {
		// check reCAPTCHA submission
		$resp = recaptcha_check_answer($recaptcha_private_key,
										$_SERVER["REMOTE_ADDR"],
										$_POST["recaptcha_challenge_field"],
										$_POST["recaptcha_response_field"]);
		if (!$resp->is_valid) {
			$body->global_vars['-ERRORS'][] = "The reCAPTCHA wasn't entered correctly. Go back and try it again. " .
			                                  "(reCAPTCHA said: " . $resp->error . ")";
			$dup_check_ok = false;
		}
	}
}

if ($_REQUEST['_submit']  &&  $dup_check_ok) {

	if ($_REQUEST['user_insert_Password'] != $_REQUEST['password2'])
		$_REQUEST['user_insert_Password'] = '';
	else {
		$s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
		$salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
		$_REQUEST['user_insert_Password'] = crypt($_REQUEST['user_insert_Password'], $salt);
	}

	$firstname =    preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_firstname']);
	$lastname =     preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_lastname']);
	$email =        preg_replace('/\s+/s', '', $_REQUEST['user_insert_EMail']);
	$url =          preg_replace('/\s+/s', '', $_REQUEST['user_insert_URL']);
	$organisation = preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_Organisation']);
	$interests =    preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_Interests']);
	$department =   preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_Department']);
	$address =      preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_Address']);
	$city =         preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_City']);
	$state =        preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_State']);

	$tmwikiname = preg_replace('/ /', '', $firstname . $lastname);

	$body->input_check();

	if ($body->satisfied) {

		$rv = $body->execute();
		$usr_id = mysql_insert_id();

		if ($usr_id) {
			// set Realname field
			mysql_query("update ".USERS_DATABASE.".Users set Realname=concat(firstname,' ',lastname) where Id=$usr_id");


			$model_usr_id = $_REQUEST['model_usr_id'];
			$res = mysql_query("select WordLimit from ".USERS_DATABASE.".Users where Id=$model_usr_id");
			$row = mysql_fetch_row($res);
			$word_limit = intval($row[0]);

			mysql_query("update ".USERS_DATABASE.".Users set WordLimit='".addslashes($word_limit)."' where Id=$usr_id");


			/* copy tags from the model_user */
			$res = mysql_query("select tag_Text from usrTags where tag_UGrpID=$model_usr_id");
			$values = '';
			while ($row = mysql_fetch_row($res)) {
				if ($values) $values .= ', ';
				$values .= '("'.addslashes($row[0]).'", ' . $usr_id . ')';
			}
			if ($values)
				mysql_query("insert into usrTags (tag_Text, tag_UGrpID) values $values");


			/* copy ignored_hyperlink_texts from the model_user */
			$res = mysql_query("select hyp_text from ignored_hyperlink_texts where hyp_usr_id=$model_usr_id");
			$values = '';
			while ($row = mysql_fetch_row($res)) {
				if ($values) $values .= ', ';
				$values .= '("'.addslashes($row[0]).'", ' . $usr_id . ')';
			}
			if ($values)
				mysql_query("insert into ignored_hyperlink_texts values $values");


			/* copy saved searches from the model_user */

			$res = mysql_query("select ss_name, ss_query from saved_searches where ss_usr_id=$model_usr_id");
			$values = '';
			$now = addslashes(date('Y-m-d H:i:s'));
			while ($row = mysql_fetch_row($res)) {
				if ($values) $values .= ', ';
				$values .= '("'.addslashes($row[0]).'", "'.addslashes($row[1]).'", ' . $usr_id . ', "' . $now . '", "' . $now . '")';
			}

			if ($values)
				mysql_query("insert into saved_searches (ss_name, ss_query, ss_usr_id, ss_added, ss_modified) values $values");


			/* mapping of model user's tag_ID to new user's tag_ID */
			$kwd_map = mysql__select_assoc("usrTags A
			                      left join usrTags B on B.tag_Text=A.tag_Text and B.tag_UGrpID=$usr_id",
			                                'A.tag_ID', 'B.tag_ID', "A.tag_UGrpID=$model_usr_id");

			$res = mysql_query("select * from usrBookmarks where bkm_UGrpID = $model_usr_id");	// model user
			while ($row = mysql_fetch_assoc($res)) {
				// add a new bookmark for each of the model user's bookmarks
				// (all fields the same except for user id and keyword id)

				unset($row['bkm_ID']);

				$row['bkm_UGrpID'] = $usr_id;
				$row['bkm_Added'] = date('Y-m-d H:i:s');
				$row['bkm_Modified'] = date('Y-m-d H:i:s');

				mysql__insert('usrBookmarks', $row);
			}


			/* for each of the model user's kwd_link entries, make a corresponding entry for the new user */
			/* hold onto your hats, folks: this is a five-table join across three tables! */
			$res = mysql_query(
'select NEWUSER_BKMK.bkm_ID, NEWUSER_KWD.tag_ID, MODUSER_KWDL.kwl_order, MODUSER_KWDL.kwl_rec_id
   from usrBookmarks NEWUSER_BKMK left join usrBookmarks MODUSER_BKMK on NEWUSER_BKMK.bkm_recID=MODUSER_BKMK.bkm_recID
                                                               and MODUSER_BKMK.bkm_UGrpID='.$model_usr_id.'
                               left join usrRecTagLinks MODUSER_KWDL on MODUSER_KWDL.kwl_pers_id=MODUSER_BKMK.bkm_ID
                               left join usrTags MODUSER_KWD on MODUSER_KWD.tag_ID=MODUSER_KWDL.kwl_kwd_id
                               left join usrTags NEWUSER_KWD on NEWUSER_KWD.tag_Text=MODUSER_KWD.tag_Text
                                                             and NEWUSER_KWD.tag_UGrpID='.$usr_id.'
  where NEWUSER_BKMK.bkm_UGrpID='.$usr_id.' and NEWUSER_KWD.tag_ID is not null'
			);
			$insert_pairs = array();
			while ($row = mysql_fetch_row($res))
				array_push($insert_pairs, '(' . intval($row[0]) . ',' . intval($row[1]) . ',' . intval($row[2]) . ',' . intval($row[3]) . ')');
			if ($insert_pairs)
				mysql_query('insert into usrRecTagLinks (kwl_pers_id, kwl_kwd_id, kwl_order, kwl_rec_id) values ' . join(',', $insert_pairs));

mysql_connection_localhost_overwrite(DATABASE);
/* END HEURIST STUFF */


			// add user to Heurist and TMWiki groups
			mysql_query("insert into ".USERS_DATABASE.".UserGroups (ug_user_id, ug_group_id) values ($usr_id, 2), ($usr_id, 4)");

			if ($_REQUEST['register']) {
				mysql_query("update ".USERS_DATABASE.".Users set Active='N' where Id=$usr_id");

				// send email to admin with link to approve page
				$email_text =
"There is a Heurist user registration awaiting approval.

The user details submitted are:

First name:    $firstname
Last name:     $lastname
Email address: $email
URL:           $url
Organisation:  $organisation
Interests:     $interests
Department:    $department
Address:       $address
City:          $city
State:         $state

Go to the address below to review further details and approve the registration:

".HEURIST_URL_BASE."admin/users/edit.php?approve=1&Id=$usr_id

";
				$admins = mysql__select_array("UserGroups left join Users on Id = ug_user_id",
				                              "EMail",
				                              "ug_group_id = " . HEURIST_ADMIN_GROUP_ID . " and ug_role = 'admin'");
				if (! @$admins  ||  count($admins) === 0) {
					$admins = array("info@acl.arts.usyd.edu.au");
				}
				mail(join(", ", $admins), "Heurist User Registration: $firstname $lastname [$email]", $email_text, 'From: root');

				header('Location: '. HEURIST_URL_BASE.'admin/users/register_success.html');
			}

			$body->global_vars['new-user-id'] = $usr_id;
		}
	}
}
$body->render();

?>
