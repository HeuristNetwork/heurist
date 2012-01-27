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
/*<!--  loadRecordDate.php

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

  -->
*/


/* Take rec_ID or bkm_ID, fill in window.HEURIST.record.bibID and window.HEURIST.record.bkmkID as appropriate */
/* FIXME: leave around some useful error messages */

if (! defined("SAVE_URI")) {
	define("SAVE_URI", "disabled");
}

/* define JSON_RESPONSE to strip out the JavaScript commands;
 * just output the .record object definition
 */

if (! defined("JSON_RESPONSE")) {
	require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
	require_once("dbMySqlWrappers.php");
	require_once("getRecordInfoLibrary.php");
	if (! is_logged_in()) return;

	header('Content-type: text/javascript');
}
preg_match("/^.*\/([^\/\.]+)/",$_SERVER['HTTP_REFERER'],$matches);
$refer = $matches[1];
$isPopup = false;
if ($refer == "formEditRecordPopup") {
	$isPopup = true;
}
//error_log(print_r($_SERVER,true));
mysql_connection_db_select(DATABASE);

list($rec_id, $bkm_ID, $replaced) = getResolvedIDs(@$_REQUEST["recID"],@$_REQUEST['bkmk_id']);

if (! $rec_id) {
	// record does not exist
	$record = null;
} else if ($replaced) {
	// the record has been deprecated
	$record = array();
	$record["replacedBy"] = $rec_id;
} else {
	$record = getBaseProperties($rec_id, $bkm_ID);
error_log("base Properties".print_r($record,true));
	if (@$record["workgroupID"] && $record["workgroupID"] != get_user_id() &&
			$record[@"visibility"] == "hidden"  &&
			! $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"][$record["workgroupID"]]) {
		// record is hidden and user is not the owner or a member of owning workgroup
		$record = array();
		$record["denied"] = true;
	} else {
		$record["bdValuesByType"] = getAllBibDetails($rec_id);
		$record["reminders"] = getAllReminders($rec_id);
		$record["comments"] = getAllComments($rec_id);
		$record["workgroupTags"] = getAllworkgroupTags($rec_id);
		$record["relatedRecords"] = getAllRelatedRecords($rec_id);
		$record["rtConstraints"] = getRectypeConstraints($record['rectypeID']);
		$record["retrieved"] = date('Y-m-d H:i:s');	// the current time according to the server
	}
}

?>

<?php if (! defined("JSON_RESPONSE")) {
		if ($isPopup) {?>
if (! window.HEURIST) window.HEURIST = {};
if (! window.HEURIST.edit) window.HEURIST.edit = {};
window.HEURIST.edit.record = <?= json_format($record) ?>;
if (top.HEURIST.fireEvent) top.HEURIST.fireEvent(window, "heurist-record-loaded");
<?php } else { ?>
if (! window.HEURIST) window.HEURIST = {};
if (! window.HEURIST.edit) window.HEURIST.edit = {};
window.HEURIST.edit.record = <?= json_format($record) ?>;
if (top.HEURIST.fireEvent) top.HEURIST.fireEvent(window, "heurist-record-loaded");
<?php }
	} else { ?>
<?= json_format($record) ?>
<?php }
/***** END OF OUTPUT *****/


?>
