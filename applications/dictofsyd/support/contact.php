<?php

/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


require_once("recaptcha/recaptchalib.php");

$recaptcha_private_key = "6LekFwkAAAAAALP7blbfKsym_LlhHNJ5LOkEY84Q";

$mode = $_REQUEST['mode'];
$name = $_REQUEST['name'];
$email = $_REQUEST['email'];
$phone = $_REQUEST['phone'];
$message = htmlentities($_REQUEST['message'], ENT_QUOTES);

// check reCAPTCHA submission
$resp = recaptcha_check_answer($recaptcha_private_key,
                               $_SERVER["REMOTE_ADDR"],
                               $_POST["recaptcha_challenge_field"],
                               $_POST["recaptcha_response_field"]);
if (! $resp->is_valid) {
	header('Location: ' . $mode . '.html?error=' . $resp->error .
		'&name=' . urlencode($name) .
		'&email=' . urlencode($email) .
		'&phone=' . urlencode($phone) .
		'&message=' . urlencode($message));
	exit;
}

$email = '
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	</head>
	<body>
		<h2>Dictionary of Sydney website message (' . $mode . ')</h2>
		<table>
			<tr>
				<th align="left">Name: </th>
				<td align="left">'.$name.'</td>
			</tr>
			<tr><td align="left" colspan="2">&nbsp;</td></tr>
			<tr>
				<th align="left">Email: </th>
				<td align="left">'.$email.'</td>
			</tr>
			<tr><td align="left" colspan="2">&nbsp;</td></tr>
			<tr>
				<th align="left">Phone: </th>
				<td align="left">'.$phone.'</td>
			</tr>
			<tr><td align="left" colspan="2">&nbsp;</td></tr>
			<tr>
				<th align="left">Message: </th>
				<td align="left">'.$message.'</td>
			</tr>
		</table>
	</body>
	</html>
';

$subject = 'DoS main website ' . ($mode == 'contact' ? 'Contact' : 'Contribute');
$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
$headers .= 'From: noreply@dictionaryofsydney.org';


mail('info@dictionaryofsydney.org', $subject, $email, $headers);
header('Location: ' . $mode . '.html?success=1');
