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



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

function sendReminderEmail($reminder, $USERS_DATABASE, $HOST, $BASE_URL) {

	if (!$USERS_DATABASE){
		$USERS_DATABASE = USERS_DATABASE;
		$dbName = HEURIST_DBNAME;
	}else{
		$dbName = substr(HEURIST_DBNAME, strlen(HEURIST_DB_PREFIX));
	}
	if ($HOST === NULL) $HOST = HOST;
	if ($BASE_URL === NULL) $BASE_URL = HEURIST_BASE_URL;

	$recipients = array();
	if (@$reminder['rem_ToEmail']) {
		array_push($recipients, array(
			"email" => $reminder['rem_ToEmail'],
			"e"		=> $reminder['rem_ToEmail'],
			"u"		=> null));
	}
	else if (@$reminder['rem_ToUserID']) {
		$res = mysql_query('select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail from '.$USERS_DATABASE.'.sysUGrps usr where usr.ugr_Type = "User" and usr.ugr_ID = '.$reminder['rem_ToUserID']);
		$row = mysql_fetch_assoc($res);
		if ($row) {
			array_push($recipients, array(
				"email" => $row['ugr_FirstName'].' '.$row['ugr_LastName'].' <'.$row['ugr_eMail'].'>',
				"e"		=> null,
				"u"		=> $reminder['rem_ToUserID']));
		}
	}
	else if (@$reminder['rem_ToWorkgroupID']) {
		$res = @$reminder['rem_ID']
				? mysql_query('select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail,usr.ugr_ID
							   from '.$USERS_DATABASE.'.sysUsrGrpLinks left join '.$USERS_DATABASE.'.sysUGrps usr on ugl_UserID=usr.ugr_ID
							   left join usrRemindersBlockList on rbl_UGrpID=usr.ugr_ID and rbl_RemID = '.$reminder['rem_ID'].'
							   where ugl_GroupID = '.$reminder['rem_ToWorkgroupID'].' and isnull(rbl_ID)')
				: mysql_query('select usr.ugr_FirstName,usr.ugr_LastName,ugr_eMail,usr.ugr_ID
							   from '.$USERS_DATABASE.'.sysUsrGrpLinks left join '.$USERS_DATABASE.'.sysUGrps usr on ugl_UserID=usr.ugr_ID
							   where ugl_GroupID = '.$reminder['rem_ToWorkgroupID']);
		while ($row = mysql_fetch_assoc($res))
			array_push($recipients, array(
				"email" => $row['ugr_FirstName'].' '.$row['ugr_LastName'].' <'.$row['ugr_eMail'].'>',
				"e"		=> null,
				"u"		=> $row['ugr_ID']));
	}

	$email_headers = 'From: Heurist reminder service <no-reply@'.$HOST.'>';

	$res = mysql_query('select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail from '.$USERS_DATABASE.'.sysUGrps usr where usr.ugr_Type = "User" and usr.ugr_ID = '.$reminder['rem_OwnerUGrpID']);
	$owner = mysql_fetch_assoc($res);
	if ($owner) {
		if (@$reminder['rem_ToEmail']  || (@$reminder['rem_user_id']  &&  @$reminder['rem_ToUserID'] != @$reminder['rem_OwnerUGrpID']))
			$email_headers .= "\r\nCc: ".$owner['ugr_FirstName'].' '.$owner['ugr_LastName'].' <'.$owner['ugr_eMail'].'>';
		$email_headers .= "\r\nReply-To: ".$owner['ugr_FirstName'].' '.$owner['ugr_LastName'].' <'.$owner['ugr_eMail'].'>';
	}

	$res = mysql_query('select rec_Title, rec_OwnerUGrpID, rec_NonOwnerVisibility, grp.ugr_Name from Records '.
						'left join '.$USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=rec_OwnerUGrpID and grp.ugr_Type != "User" '.
						'where rec_ID = '.$reminder['rem_RecID']);
	$bib = mysql_fetch_assoc($res);

	$email_subject = '[Heurist] "'.$bib['rec_Title'].'"';

	if (@$reminder['rem_ToUserID'] != @$reminder['rem_OwnerUGrpID'])
		$email_subject .= ' from ' . $owner['ugr_FirstName'].' '.$owner['ugr_LastName'];

	foreach($recipients as $recipient) {
		$email_text = 'Reminder From: ' . ($reminder['rem_ToUserID'] == $reminder['rem_OwnerUGrpID'] ? 'you'
											: $owner['ugr_FirstName'].' '.$owner['ugr_LastName'].' <'.$owner['ugr_eMail'].'>') . "\n\n"
					. 'For: "'.$bib['rec_Title'].'"' . "\n\n"
					. 'URL: '.$BASE_URL.'?w=all&db='.$dbName.'&q=ids:'.$reminder['rem_RecID'] . "\n\n";

		if ($bib['rec_OwnerUGrpID'] && $bib['rec_NonOwnerVisibility'] == 'hidden') {
			$email_text .= "Note: Record belongs to workgroup ".$bib['ugr_Name'] . "\n"
							."You must be logged in to Heurist and a member of this workgroup to view it". "\n\n";
		}

		$email_text .= 'Message: '.$reminder['rem_Message'] . "\n\n";

		if (@$reminder['rem_ID']  &&  $reminder['rem_Freq'] != "once") {
			$email_text .= "-------------------------------------------\n\n"
						.  "You will receive this reminder " . $reminder['rem_Freq'] . "\n"
						.  "Click below if you do not wish to receive this reminder again:\n\n"
						.  $BASE_URL."records/reminders/deleteReminder.php?r=".$reminder['rem_ID']
						. "db=".$dbName
						.  ($recipient['u'] ? "&u=".$recipient['u'] : "&e=".$recipient['e']) . "&h=".$reminder['rem_Nonce'] . "\n\n";
		} else {
			$email_text .= "-------------------------------------------\n\n"
						.  "You will not receive this reminder again.\n\n";
		}
		$email_text .= "-------------------------------------------\n"
					.  "This email was generated by Heurist:\n"
					.  "http://".$HOST."\n";

		$email_headers .= "\r\nContent-Type: text/plain; charset=utf-8\r\n";

		mail($recipient['email'], '=?utf-8?B?'.base64_encode($email_subject).'?=', $email_text, $email_headers);
	}

	return true;
}

?>
