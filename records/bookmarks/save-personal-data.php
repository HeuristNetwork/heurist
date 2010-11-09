<?php

/* Save personal data */

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

if (! is_logged_in()) return;

mysql_connection_db_overwrite(DATABASE);

$bkm_ID = intval($_POST["bkmk_id"]);
if ($bkm_ID  &&  $_POST["save-mode"] == "edit") {
	if (array_key_exists("tagString", $_POST)) {
		$tagString = doKeywordInsertion($bkm_ID);
	} else  $tagString = NULL;

	$updatable = array(
	/* map database column to array($_POST variable name, JSON response name) */
		"bkm_PwdReminder" => array("password-reminder", "passwordReminder"),
		"bkm_Rating" => array("rating", "overallRating"),
		"pers_notes" => array("quick-notes", "quickNotes")
	);
	$updates = array();
	foreach ($updatable as $colName => $varNames) {
		if (array_key_exists($varNames[0], $_POST))
			$updates[$colName] = $_POST[$varNames[0]];
	}
	mysql__update("usrBookmarks", "bkm_ID=$bkm_ID and bkm_UGrpID=".get_user_id(), $updates);

	$res = mysql_query("select " . join(", ", array_keys($updates)) .
				" from usrBookmarks where bkm_ID=$bkm_ID and bkm_UGrpID=".get_user_id());
	if (mysql_num_rows($res) == 1) {
		$dbVals = mysql_fetch_assoc($res);
		$hVals = array();
		foreach ($dbVals as $colName => $val) $hVals[$updatable[$colName][1]] = $val;

		if ($tagString !== NULL) $hVals["tagString"] = $tagString;
		print "(" . json_format($hVals) . ")";
	}
	else if ($tagString !== NULL) {
		print "({tagString: \"" . slash($tagString) . "\"})";
	}
}


function doKeywordInsertion($bkm_ID) {
	$kwds = mysql__select_array("keyword_links, usrTags",
	                            "tag_Text", "kwl_pers_id=$bkm_ID and tag_ID=kwl_kwd_id and tag_UGrpID=".get_user_id()." order by kwl_order, kwl_id");
	$tagString = join(",", $kwds);

	// Nothing to do here
	if (strtolower(trim($_POST["tagString"])) == strtolower(trim($tagString))) return;


	$keywords = array_filter(array_map("trim", explode(",", str_replace("\\", "/", $_POST["tagString"]))));	// replace backslashes with forwardslashes
	$kwd_map = mysql__select_assoc("usrTags", "trim(lower(tag_Text))", "tag_ID",
	                               "tag_UGrpID=".get_user_id()." and tag_Text in (\"".join("\",\"", array_map("addslashes", $keywords))."\")");

	$kwd_ids = array();
	foreach ($keywords as $keyword) {
		$keyword = preg_replace('/\\s+/', ' ', trim($keyword));
		if (@$kwd_map[strtolower($keyword)]) {
			$kwd_id = $kwd_map[strtolower($keyword)];
		} else {
			mysql_query("insert into usrTags (tag_Text, tag_UGrpID) values (\"" . addslashes($keyword) . "\", " . get_user_id() . ")");
			$kwd_id = mysql_insert_id();
		}
		array_push($kwd_ids, $kwd_id);
	}

	$res = mysql_query("select bkm_recID from usrBookmarks where bkm_ID=$bkm_ID");
	$rec_id = mysql_fetch_row($res);  $rec_id = $rec_id[0];
	if (! $rec_id) $rec_id = "NULL";

	// Delete all non-workgroup tags for this bookmark
	mysql_query("delete keyword_links from keyword_links, usrTags where kwl_pers_id = $bkm_ID and tag_ID=kwl_kwd_id and ???kwd_wg_id is null");

	if (count($kwd_ids) > 0) {
		$query = "";
		for ($i=0; $i < count($kwd_ids); ++$i) {
			if ($query) $query .= ", ";
			$query .= "($bkm_ID, $rec_id, ".($i+1).", ".$kwd_ids[$i].")";
		}
		$query = "insert into keyword_links (kwl_pers_id, kwl_rec_id, kwl_order, kwl_kwd_id) values " . $query;
		mysql_query($query);
	}

	// return new keyword string
	$kwds = mysql__select_array("keyword_links, usrTags",
	                            "tag_Text", "kwl_bkm_ID=$bkm_ID and tag_ID=kwl_kwd_id and tag_UGrpID=".get_user_id()." order by kwl_order, kwl_id");
	return join(",", $kwds);
}

?>
