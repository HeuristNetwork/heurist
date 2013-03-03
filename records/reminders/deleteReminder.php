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

?>

<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

$r = intval(@$_REQUEST["r"]);
$u = intval(@$_REQUEST["u"]);
$e = @$_REQUEST["e"];
$h = @$_REQUEST["h"];

if (! $r) return;

if ($u) {
	if (! is_logged_in()  ||  $u != get_user_id()) {
		header("Location: " . HEURIST_BASE_URL . "common/connect/login.php?logout=1");
		return;
	}
}

mysql_connection_overwrite(DATABASE);

$res = mysql_query("select rem_Nonce from usrReminders where rem_ID = ".$r);
$row = mysql_fetch_assoc($res);
if ($h != $row["rem_Nonce"]) {
	return;
}

if ($e) {
	mysql_query("delete from usrReminders where rem_ID = ".$r." and rem_ToEmail = '".addslashes($e)."'");
	if (! mysql_affected_rows()) {
		return;
	}
} else if ($u) {
	mysql_query("delete from usrReminders where rem_ID = ".$r." and rem_ToUserID = '".$u."'");
	if (! mysql_affected_rows()) {
		// must be a group - insert a blacklist entry
		mysql_query("insert into usrRemindersBlockList (rbl_RemID, rbl_UGrpID) values (".$r.", ".$u.")");
		if (! mysql_affected_rows()) {
			return;
		}
	}
} else {
	return;
}

?>
<html>
 <body>
  <p>Reminder removed</p>
  <p><a href=..>Heurist home</a></p>
 </body>
</html>
