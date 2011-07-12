<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

// TO DO: set flag from sysIdentification.sys_AllowRegistration and exit here if false

if (!@$_REQUEST['register'] && !(is_logged_in() && is_admin())) {
	header('Location: '.HEURIST_URL_BASE.'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

$recaptcha_public_key = "6LdOkgQAAAAAAJA5_pdkrwcKA-VFPPdihgoLiWmT";
$recaptcha_private_key = "6LdOkgQAAAAAALRx8NbUn9HL50ykaTjf1dv5G3oq";

mysql_connection_overwrite(DATABASE);

$query = mysql_query("SELECT ugr_FirstName, ugr_LastName, ugr_eMail FROM sysUGrps WHERE ugr_ID=2");
error_log("--- ".mysql_error());
$details = mysql_fetch_row($query);
$fullName = $details[0] . " " . $details[1];
$eMail = $details[2];
echo '<script type="text/javascript">';
echo 'var adminDetails = "Email: <a href=\'mailto:'.$eMail.'\'>'.$fullName.' &lt;'.$eMail.'&gt;</a>";';
echo '</script>';

$template = file_get_contents('findAddUser.html');

$lexer = new Lexer($template);

$body = new BodyScope($lexer);

$body->global_vars['recaptcha'] = "";
$body->global_vars['dbname'] = HEURIST_DBNAME;

if (@$_REQUEST['register']) {
	$body->global_vars['register'] = 1;
	require_once(dirname(__FILE__)."/../../external/recaptcha/recaptchalib.php");
	$body->global_vars['recaptcha'] = recaptcha_get_html($recaptcha_public_key);
} else {
	$body->global_vars['register'] = 0;
}

$body->global_vars['new-user-id'] = 0;


$body->global_vars['model-user-dropdown'] = '<select name="model_usr_id">'."\n";
$res = mysql_query("select usr.ugr_ID, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as realname from ".USERS_DATABASE.".sysUGrps usr where usr.ugr_IsModelUser = 1" . (@$_REQUEST['register'] ? ' limit 1' : ''));
while ($row = mysql_fetch_assoc($res)) {
	$body->global_vars['model-user-dropdown'] .= ' <option value="'.$row['ugr_ID'].'">'.$row['realname'].'</option>'."\n";
}
$body->global_vars['model-user-dropdown'] .= '</select>'."\n";
if (@$_REQUEST['register']) {
	$body->global_vars['model-user-dropdown'] = '<input type="hidden" name="model_usr_id" value="96">'."\n";
}


$body->verify();


$dup_check_ok = true;
if (@$_REQUEST['_submit']) {

	// check for duplicate fields
	$res = mysql_query('select ugr_ID from '.USERS_DATABASE.'.sysUGrps usr where usr.ugr_Name = "'.$_REQUEST['user_insert_ugr_Name'].'"');
	if (mysql_num_rows($res) > 0) {
		$body->global_vars['-ERRORS'][] = 'The Username you have chosen is already in use.  Please choose another.';
		$dup_check_ok = false;
	}
	$res = mysql_query('select ugr_ID from '.USERS_DATABASE.'.sysUGrps usr where ugr_eMail = "'.$_REQUEST['user_insert_ugr_eMail'].'"');
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
		if (!$resp->is_valid && $resp->error != "true") {
			$body->global_vars['-ERRORS'][] = "The reCAPTCHA wasn't entered correctly. Go back and try it again. " .
			                                  "(reCAPTCHA said: " . $resp->error . ")";
			$dup_check_ok = false;
		}
	}
}

if (@$_REQUEST['_submit']  &&  $dup_check_ok) {

	if (@$_REQUEST['user_insert_ugr_Password'] != $_REQUEST['password2'])
		$_REQUEST['user_insert_ugr_Password'] = '';
	else {
		$s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
		$salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
		$_REQUEST['user_insert_ugr_Password'] = crypt($_REQUEST['user_insert_ugr_Password'], $salt);
	}

	$firstname =    preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_FirstName']);
	$lastname =     preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_LastName']);
	$email =        preg_replace('/\s+/s', '', $_REQUEST['user_insert_ugr_eMail']);
	$url =          preg_replace('/\s+/s', '', $_REQUEST['user_insert_ugr_URLs']);
	$organisation = preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_Organisation']);
	$interests =    preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_Interests']);
	$department =   preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_Department']);
	$address =      preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_Address']);
	$city =         preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_City']);
	$state =        preg_replace('/\s+/s', ' ', $_REQUEST['user_insert_ugr_State']);

	$tmwikiname = preg_replace('/ /', '', $firstname . $lastname);

	$body->input_check();

	if ($body->satisfied) {

		$rv = $body->execute();
		$usr_id = mysql_insert_id();

		if ($usr_id) {


			$model_usr_id = $_REQUEST['model_usr_id'];
			$res = mysql_query("select ugr_MinHyperlinkWords from ".USERS_DATABASE.".sysUGrps usr where ugr_ID=$model_usr_id");
			$row = mysql_fetch_row($res);
			$word_limit = intval($row[0]);

			mysql_query("update ".USERS_DATABASE.".sysUGrps usr set ugr_MinHyperlinkWords='".addslashes($word_limit)."' where ugr_ID=$usr_id");


			/* copy tags from the model_user */
			$res = mysql_query("select tag_Text from usrTags where tag_UGrpID=$model_usr_id");
			$values = '';
			while ($row = mysql_fetch_row($res)) {
				if ($values) $values .= ', ';
				$values .= '("'.addslashes($row[0]).'", ' . $usr_id . ')';
			}
			if ($values)
				mysql_query("insert into usrTags (tag_Text, tag_UGrpID) values $values");


			/* copy usrHyperlinkFilter from the model_user */
			$res = mysql_query("select hyf_String from usrHyperlinkFilter where hyf_UGrpID=$model_usr_id");
			$values = '';
			while ($row = mysql_fetch_row($res)) {
				if ($values) $values .= ', ';
				$values .= '("'.addslashes($row[0]).'", ' . $usr_id . ')';
			}
			if ($values)
				mysql_query("insert into usrHyperlinkFilter values $values");


			/* copy saved searches from the model_user */

			$res = mysql_query("select svs_Name, svs_Query from usrSavedSearches where svs_UGrpID=$model_usr_id");
			$values = '';
			$now = addslashes(date('Y-m-d H:i:s'));
			while ($row = mysql_fetch_row($res)) {
				if ($values) $values .= ', ';
				$values .= '("'.addslashes($row[0]).'", "'.addslashes($row[1]).'", ' . $usr_id . ', "' . $now . '", "' . $now . '")';
			}

			if ($values)
				mysql_query("insert into usrSavedSearches (svs_Name, svs_Query, svs_UGrpID, svs_Added, svs_Modified) values $values");


			/* mapping of model user's tag_ID to new user's tag_ID */
			$kwd_map = mysql__select_assoc("usrTags A
			                      left join usrTags B on B.tag_Text=A.tag_Text and B.tag_UGrpID=$usr_id",
			                                'A.tag_ID', 'B.tag_ID', "A.tag_UGrpID=$model_usr_id");

			$res = mysql_query("select * from usrBookmarks where bkm_UGrpID = $model_usr_id");	// model user
			while ($row = mysql_fetch_assoc($res)) {
				// add a new bookmark for each of the model user's bookmarks
				// (all fields the same except for user id)

				unset($row['bkm_ID']);

				$row['bkm_UGrpID'] = $usr_id;
				$row['bkm_Added'] = date('Y-m-d H:i:s');
				$row['bkm_Modified'] = date('Y-m-d H:i:s');

				mysql__insert('usrBookmarks', $row);
			}


			/* for each of the model user's kwd_link entries, make a corresponding entry for the new user */
			/* hold onto your hats, folks: this is a five-table join across three tables! */
			$res = mysql_query(
'select NEWUSER_KWD.tag_ID, MODUSER_KWDL.rtl_Order, MODUSER_KWDL.rtl_RecID
   from usrBookmarks NEWUSER_BKMK left join usrBookmarks MODUSER_BKMK on NEWUSER_BKMK.bkm_RecID=MODUSER_BKMK.bkm_RecID
                                                               and MODUSER_BKMK.bkm_UGrpID='.$model_usr_id.'
                               left join usrRecTagLinks MODUSER_KWDL on MODUSER_KWDL.rtl_RecID=MODUSER_BKMK.bkm_RecID
                               left join usrTags MODUSER_KWD on MODUSER_KWD.tag_ID=MODUSER_KWDL.rtl_TagID
                               left join usrTags NEWUSER_KWD on NEWUSER_KWD.tag_Text=MODUSER_KWD.tag_Text
                                                             and NEWUSER_KWD.tag_UGrpID='.$usr_id.'
  where NEWUSER_BKMK.bkm_UGrpID='.$usr_id.' and NEWUSER_KWD.tag_ID is not null'
			);
			$insert_pairs = array();
			while ($row = mysql_fetch_row($res))
				array_push($insert_pairs, '(' . intval($row[0]) . ',' . intval($row[1]) . ',' . intval($row[2]) . ')');
			if ($insert_pairs)
				mysql_query('insert into usrRecTagLinks (rtl_TagID, rtl_Order, rtl_RecID) values ' . join(',', $insert_pairs));

/* END HEURIST STUFF */


			// add user to Heurist and TMWiki groups
// TODO: check this is necessary			mysql_query("insert into ".USERS_DATABASE.".sysUsrGrpLinks (ugl_UserID, ugl_GroupID) values ($usr_id, 2), ($usr_id, 4)");

			if ($_REQUEST['register']) {
				mysql_query("update ".USERS_DATABASE.".sysUGrps usr set ugr_Enabled='N' where ugr_ID=$usr_id");

				// send email to admin with link to approve page
				$email_text =
"There is a Heurist user registration awaiting approval.

The user details submitted are:

Database name: ".DATABASE."
First name:    $firstname
Last name:     $lastname
Email address: $email
URLs:           $url
Organisation:  $organisation
Interests:     $interests
Department:    $department
Address:       $address
City:          $city
State:         $state

Go to the address below to review further details and approve the registration:

".HEURIST_URL_BASE."admin/ugrps/editUser.php?db=".HEURIST_DBNAME."&approve=1&Id=$usr_id

";
				$admins = mysql__select_array("sysUsrGrpLinks left join sysUGrps usr on ugr_ID = ugl_UserID",
				                              "ugr_eMail",
				                              "ugl_GroupID = " . HEURIST_DBADMIN_GROUP_ID . " and ugl_Role = 'admin'");
				if (! @$admins  ||  count($admins) === 0) {
					$admins = array("info@acl.arts.usyd.edu.au");
				}
				mail(join(", ", $admins), "Heurist User Registration: $firstname $lastname [$email]", $email_text, 'From: root');

				header('Location: '. HEURIST_URL_BASE.'admin/ugrps/msgRegistrationSuccess.html?db='.HEURIST_DBNAME);
			}

			$body->global_vars['new-user-id'] = $usr_id;
		}
	}
}
$body->render();
?>