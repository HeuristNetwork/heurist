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
* brief description of file
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


	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	if (! is_logged_in()) return;

	mysql_connection_select(DATABASE);

	/*****DEBUG****///error_log("request is ".print_r($_REQUEST,true)); //>>>>DEBUG

	if (@$_REQUEST['send_notification']) {
		$notification_sent_message = handle_notification();
		$success = preg_match('/^Notification email sent/', $notification_sent_message);

		if ($success) {
		?>
		<html>
			<body class="popup" width=700 height=160 style="font-size: 11px;" onload="setTimeout(function() { window.close(); }, 10); var my_alert = top.alert; top.setTimeout(function() { my_alert('<?= $notification_sent_message ?>'); }, 10);">
			</body>
		</html>
		<?php
			return;
		}
	}


?>
<html>
	<head>
		<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">

		<title>Share records</title>
		<script type="text/javascript">

			function fill_bib_ids() {
				var bib_ids_elt = document.getElementById('bib_ids');
				if (! bib_ids_elt.value) {
					var bib_ids_list = top.HEURIST.search.getSelectedRecIDs().get();
					bib_ids_elt.value = bib_ids_list.join(',');
				}
			}


			function confirm_notification() {
				var e;
				e = document.getElementById('notify_group');
				if (e  &&  e.selectedIndex) return confirm('Notify all '+(e.options[e.selectedIndex]).text+' members?');

				e = document.getElementById('notify_coll_grp');
				if (e  &&  e.selectedIndex) return confirm('Notify all '+(e.options[e.selectedIndex]).text+' members?');

				e = document.getElementById('notify_person');
				if (e  &&  e.selectedIndex) return true;

				e = document.getElementById('notify_email');
				if (e  &&  e.value) return true;

				alert('Select a person or group to notify by email');

				return false;
			}

			function reset_person() { document.getElementById('notify_person').selectedIndex = 0; }
			function reset_email() { document.getElementById('notify_email').value = ''; }
			function reset_coll_grp() {
				document.getElementById('notify_coll_grp').selectedIndex = 0;
				document.getElementById('coll_grp_members_link_div').style.display = 'none';
			}
			function reset_group() {
				document.getElementById('notify_group').selectedIndex = 0;
				document.getElementById('grp_members_link_div').style.display = 'none';
			}

		</script>

	</head>

	<body class="popup" width=700 height=160 style="font-size: 11px;" onload="fill_bib_ids();">

		<?= @$notification_sent_message?$notification_sent_message:"" ?>

		<form action="sendNotificationsPopup.php" method="post" style="display: inline;">

			<div style="font-weight: bold; margin-bottom: 3px;">
				Share these records with other users via email:
			</div>
			&nbsp;
			<?php
				$res = mysql_query('select usr.'.USERS_ID_FIELD.',concat(usr.'.USERS_FIRSTNAME_FIELD.'," ",usr.'.USERS_LASTNAME_FIELD.') as fullname
					from '.USERS_DATABASE.'.'.USERS_TABLE.' usr
					where usr.'.USERS_ACTIVE_FIELD.'="Y" and usr.'.USERS_FIRSTNAME_FIELD.' is not null and usr.'.USERS_LASTNAME_FIELD.' is not null and !usr.ugr_IsModelUser
				order by fullname');
				if (mysql_num_rows($res)) {
				?>
				<select name="notify_person" id="notify_person" style="width: 120px;" onchange="reset_group(); reset_coll_grp(); reset_email();">
					<option value="0">Person...</option>
					<?php		while ($row = mysql_fetch_assoc($res)) { ?>
						<option value="<?=$row[USERS_ID_FIELD]?>" <?=($row[USERS_ID_FIELD]==get_user_id())? 'selected' : ''?>><?=htmlspecialchars($row['fullname'])?></option>
						<?php		} ?>
				</select>
				<?php	} ?>
			&nbsp;
			or
			<?php
				$res = mysql_query('select '.GROUPS_ID_FIELD.','.GROUPS_NAME_FIELD.'
					from '.USERS_DATABASE.'.'.USER_GROUPS_TABLE.' left join '.USERS_DATABASE.'.'.GROUPS_TABLE.' on '.GROUPS_ID_FIELD.'='.USER_GROUPS_GROUP_ID_FIELD.'
					where '.USER_GROUPS_USER_ID_FIELD.' = '.get_user_id().' and '.GROUPS_TYPE_FIELD.'="Workgroup"
					order by '.GROUPS_NAME_FIELD);
				if (mysql_num_rows($res)) {
				?>
				<select name="notify_group" id="notify_group" style="width: 120px;" onchange="reset_person(); reset_coll_grp(); reset_email(); document.getElementById('grp_members_link_div').style.display = ''; document.getElementById('grp_members_link').wg_id = this.value;">
					<option value="0">Group...</option>
					<?php		while ($row = mysql_fetch_assoc($res)) { ?>
						<option value="<?=$row[GROUPS_ID_FIELD]?>"><?=htmlspecialchars($row[GROUPS_NAME_FIELD])?></option>
						<?php		} ?>
				</select>
				<?php	} ?>
			&nbsp;
			or email:
			<input type="text" name="notify_email" id="notify_email" onfocus="reset_person(); reset_group(); reset_coll_grp();">
			<br>
			<div id="grp_members_link_div" style="text-align: center; display: none;">&nbsp;<a id="grp_members_link" href=# onclick="top.HEURIST.util.popupURL(window, '/admin/ugrps/listUsergroupMembers.html?wg_id='+this.wg_id); return false;">Show group members</a></div>
			&nbsp;
			<textarea name="notify_message" title="email message" style="width: 95%;" rows="3"
				onfocus="if (this.value=='(enter message here)') this.value='';">(enter message here)</textarea>
			<div style="width: 95%; margin-top: 3px;">
				<input type="submit" name="send_notification" id="notify_submit" value="Share" style="float: right;"
					onclick="return confirm_notification();">
			</div>

			<input type="hidden" name="bib_ids" id="bib_ids" value="<?= htmlspecialchars($_REQUEST['bib_ids']) ?>">

		</form>

	</body>
</html>
<?php

	function handle_notification() {
		function getInt($strInt){
			/*****DEBUG****///error_log("str = ".preg_replace("/[\"']/","",$strInt)." val =". intval($strInt));
			return intval(preg_replace("/[\"']/","",$strInt));
		}
		$bib_ids = array_map("getInt", explode(',', $_REQUEST['bib_ids']));
		/*****DEBUG****///error_log("bibids = ".print_r($bib_ids,true));
		if (! count($bib_ids)){
			return '<div style="color: red; font-weight: bold; padding: 5px;">(you must select at least one bookmark)</div>';
		}
		$bibIDList = join(',', $bib_ids);
		$notification_link = HEURIST_BASE_URL . 'search/search.html?db='.HEURIST_DBNAME.'&w=all&q=ids:' . $bibIDList;

		$bib_titles = mysql__select_assoc('Records', 'rec_ID', 'rec_Title', 'rec_ID in (' . $bibIDList . ')');
		$title_list = "Id      Title\n" . "------  ---------\n";
		foreach ($bib_titles as $rec_id => $rec_title)
			$title_list .= str_pad("$rec_id", 8) . $rec_title . "\n";

		$msg = '';
		if ($_REQUEST['notify_message']  &&  $_REQUEST['notify_message'] != '(enter message here)')
			$msg = '"' . $_REQUEST['notify_message'] . '"' . "\n\n";

		$res = mysql_query('select '.USERS_EMAIL_FIELD.' from '.USERS_DATABASE.'.'.USERS_TABLE.' where '.USERS_ID_FIELD.' = ' . get_user_id());
		$row = mysql_fetch_row($res);
		if ($row) $user_email = $row[0];
		mysql_connection_overwrite(DATABASE);

		$email_subject = '[HEURIST] email from ' . get_user_name();
		if (count($bib_ids) == 1) $email_subject .= ' (one reference)';
		else $email_subject .= ' (' . count($bib_ids) . ' references)';

		$email_headers = 'From: ' . get_user_name() . ' <no-reply@'.HEURIST_SERVER_NAME.'>';
		if ($user_email) {
			$email_headers .= "\r\nCc: " . get_user_name() . ' <' . $user_email . '>';
			$email_headers .= "\r\nReply-To: " . get_user_name() . ' <' . $user_email . '>';
		}

		$email_text = get_user_name()." would like to draw some records to your attention, with the following note:\n\n"
		.$msg
		."Access them and add them (if desired) to your Heurist records at:\n\n"
		.$notification_link
		."\n\n"
		."To add records, either click on the unfilled star left of the title\n"
		."or check the ones you wish to add (click _show_ if no checkboxes showing)\n"
		."and click _bookmark_ (show and bookmark are in the actions toolbar at\n"
		."the top of the search results).\n\n\n"
		."The record titles are:\n\n"
		.$title_list
		."\n\n"
		."-------------------------------------------\n"
		."This email was generated by Heurist:\n"
		.HEURIST_BASE_URL."\n";

		if ($_REQUEST['notify_group']) {
			$email_headers = preg_replace('/Cc:[^\r\n]*\r\n/', '', $email_headers);
			$res = mysql_query('select '.GROUPS_NAME_FIELD.' from '.USERS_DATABASE.'.'.GROUPS_TBALE.' where '.GROUPS_ID_FIELD.'='.intval($_REQUEST['notify_group']));
			$row = mysql_fetch_assoc($res);
			$grpname = $row[GROUPS_NAME_FIELD];
			$res = mysql_query('select '.USERS_EMAIL_FIELD.'
				from '.USERS_DATABASE.'.'.USERS_TABLE.' left join '.USERS_DATABASE.'.'.USER_GROUPS_TABLE.' on '.USER_GROUPS_USER_ID_FIELD.'='.USERS_ID_FIELD.'
				where '.USER_GROUPS_GROUP_ID_FIELD.'='.intval($_REQUEST['notify_group']));
			$count =  mysql_num_rows($res);
			while ($row = mysql_fetch_assoc($res))
				$email_headers .= "\r\nBcc: ".$row[USERS_EMAIL_FIELD];
			mail(get_user_name().' <'.$user_email.'>', $email_subject, $email_text, $email_headers);

			return 'Notification email sent to group '.$grpname.' ('.$count.' members)';
		} else if ($_REQUEST['notify_person']) {
			$res = mysql_query('select '.USERS_EMAIL_FIELD.', concat('.USERS_FIRSTNAME_FIELD.'," ",'.USERS_LASTNAME_FIELD.') as fullname from '.USERS_DATABASE.'.'.USERS_TABLE.' where '.USERS_ID_FIELD.'='.$_REQUEST['notify_person']);
			$psn = mysql_fetch_assoc($res);
			mail($psn[USERS_EMAIL_FIELD], $email_subject, $email_text, $email_headers);
			return 'Notification email sent to '.addslashes($psn['fullname']);
		} else if ($_REQUEST['notify_email']) {
			mail($_REQUEST['notify_email'], $email_subject, $email_text, $email_headers);
			return 'Notification email sent to '.addslashes($_REQUEST['notify_email']);
		} else {
			return '<div style="color: red; font-weight: bold; padding: 5px;">(you must select a group, person, or enter an email address)</div>';
		}
	}

?>
