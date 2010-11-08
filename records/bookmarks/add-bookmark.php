<?php

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

if (! is_logged_in()) return;

mysql_connection_db_overwrite(DATABASE);

header("Content-type: text/javascript");


/* chase down any "replaced by" indirections */
$rec_id = intval($_REQUEST["bib_id"]);
$res = mysql_query("select * from records where rec_id = $rec_id");
$bib = mysql_fetch_assoc($res);
if (! $bib) {
	print "{ error: \"invalid record ID\" }";
	return;
}

/* check workgroup permissions */
if ($bib["rec_wg_id"]  &&  $bib["rec_visibility"] == "Hidden") {
	error_log("select ug_group_id from ".USERS_DATABASE.".Groups where ug_user_id=" . get_user_id() . " and ug_group_id=" . intval($bib["rec_wg_id"]));
	$res = mysql_query("select ug_group_id from ".USERS_DATABASE.".Groups where ug_user_id=" . get_user_id() . " and ug_group_id=" . intval($bib["rec_wg_id"]));
	if (! mysql_num_rows($res)) {
		$res = mysql_query("select grp_name from ".USERS_DATABASE.".Groups where grp_id=" . $bib["rec_wg_id"]);
		$grp_name = mysql_fetch_row($res);  $grp_name = $grp_name[0];
		print "{ error: \"record is restricted to workgroup " . slash($grp_name) . "\" }";
		return;
	}
}


/* check -- maybe the user has this bookmarked already ..? */
$res = mysql_query("select * from usrBookmarks where bkm_recID=$rec_id and bkm_UGrpID=" . get_user_id());

if (mysql_num_rows($res) == 0) {
	/* full steam ahead */
	mysql_query("insert into usrBookmarks (bkm_recID, bkm_UGrpID, bkm_Added, bkm_Modified) values (" . $rec_id . ", " . get_user_id() . ", now(), now())");

	$res = mysql_query("select * from usrBookmarks where bkm_ID=last_insert_id()");
	if (mysql_num_rows($res) == 0) {
		print "{ error: \"internal database error while adding bookmark\" }";
		return;
	}
	$bkmk = mysql_fetch_assoc($res);
	$bkmk["keywordString"] = "";
}
else {
	$bkmk = mysql_fetch_assoc($res);
	$kwds = mysql__select_array("keyword_links left join keywords on kwd_id=kwl_kwd_id", "kwd_name", "kwl_pers_id=".$bkmk["bkm_ID"]." and kwd_usr_id=".get_user_id() . " order by kwl_order, kwl_id");
	$bkmk["keywordString"] = join(",", $kwds);
}

$record = array(
	"bkmkID" => $bkmk["bkm_ID"],
	"keywordString" => $bkmk["keywordString"],
	"interestRating" => $bkmk["pers_interest_rating"],
	"contentRating" => $bkmk["pers_content_rating"],
	"qualityRating" => $bkmk["pers_quality_rating"],
	"reminders" => array(),	// FIXME: should really import these freshly in case the bkmk already exists
	"passwordReminder" => $bkmk["bkm_PwdReminder"],
	"quickNotes" => $bkmk["pers_notes"]? $bkmk["pers_notes"] : ""
);

print json_format($record);

?>
