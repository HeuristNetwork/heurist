<?php

/* Save personal data */

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

if (! is_logged_in()) return;

mysql_connection_db_overwrite(DATABASE);

$pers_id = intval($_POST["bkmk_id"]);
if ($pers_id  &&  $_POST["save-mode"] == "edit") {
	if (array_key_exists("keywordstring", $_POST)) {
		$keywordstring = doKeywordInsertion($pers_id);
	} else  $keywordstring = NULL;

	$updatable = array(
	/* map database column to array($_POST variable name, JSON response name) */
		"pers_pwd_reminder" => array("password-reminder", "passwordReminder"),
		"pers_interest_rating" => array("interest-rating", "interestReminder"),
		"pers_content_rating" => array("content-rating", "contentRating"),
		"pers_quality_rating" => array("quality-rating", "qualityRating"),
		"pers_notes" => array("quick-notes", "quickNotes")
	);
	$updates = array();
	foreach ($updatable as $colName => $varNames) {
		if (array_key_exists($varNames[0], $_POST))
			$updates[$colName] = $_POST[$varNames[0]];
	}
	mysql__update("personals", "pers_id=$pers_id and pers_usr_id=".get_user_id(), $updates);

	$res = mysql_query("select " . join(", ", array_keys($updates)) .
				" from personals where pers_id=$pers_id and pers_usr_id=".get_user_id());
	if (mysql_num_rows($res) == 1) {
		$dbVals = mysql_fetch_assoc($res);
		$hVals = array();
		foreach ($dbVals as $colName => $val) $hVals[$updatable[$colName][1]] = $val;

		if ($keywordstring !== NULL) $hVals["keywordString"] = $keywordstring;
		print "(" . json_format($hVals) . ")";
	}
	else if ($keywordstring !== NULL) {
		print "({keywordString: \"" . slash($keywordstring) . "\"})";
	}
}


function doKeywordInsertion($pers_id) {
	$kwds = mysql__select_array("keyword_links, keywords",
	                            "kwd_name", "kwl_pers_id=$pers_id and kwd_id=kwl_kwd_id and kwd_usr_id=".get_user_id()." order by kwl_order, kwl_id");
	$keywordString = join(",", $kwds);

	// Nothing to do here
	if (strtolower(trim($_POST["keywordstring"])) == strtolower(trim($keywordString))) return;


	$keywords = array_filter(array_map("trim", explode(",", str_replace("\\", "/", $_POST["keywordstring"]))));	// replace backslashes with forwardslashes
	$kwd_map = mysql__select_assoc("keywords", "trim(lower(kwd_name))", "kwd_id",
	                               "kwd_usr_id=".get_user_id()." and kwd_name in (\"".join("\",\"", array_map("addslashes", $keywords))."\")");

	$kwd_ids = array();
	foreach ($keywords as $keyword) {
		$keyword = preg_replace('/\\s+/', ' ', trim($keyword));
		if (@$kwd_map[strtolower($keyword)]) {
			$kwd_id = $kwd_map[strtolower($keyword)];
		} else {
			mysql_query("insert into keywords (kwd_name, kwd_usr_id) values (\"" . addslashes($keyword) . "\", " . get_user_id() . ")");
			$kwd_id = mysql_insert_id();
		}
		array_push($kwd_ids, $kwd_id);
	}

	$res = mysql_query("select pers_rec_id from personals where pers_id=$pers_id");
	$rec_id = mysql_fetch_row($res);  $rec_id = $rec_id[0];
	if (! $rec_id) $rec_id = "NULL";

	// Delete all non-workgroup keywords for this bookmark
	mysql_query("delete keyword_links from keyword_links, keywords where kwl_pers_id = $pers_id and kwd_id=kwl_kwd_id and kwd_wg_id is null");

	if (count($kwd_ids) > 0) {
		$query = "";
		for ($i=0; $i < count($kwd_ids); ++$i) {
			if ($query) $query .= ", ";
			$query .= "($pers_id, $rec_id, ".($i+1).", ".$kwd_ids[$i].")";
		}
		$query = "insert into keyword_links (kwl_pers_id, kwl_rec_id, kwl_order, kwl_kwd_id) values " . $query;
		mysql_query($query);
	}

	// return new keyword string
	$kwds = mysql__select_array("keyword_links, keywords",
	                            "kwd_name", "kwl_pers_id=$pers_id and kwd_id=kwl_kwd_id and kwd_usr_id=".get_user_id()." order by kwl_order, kwl_id");
	return join(",", $kwds);
}

?>
