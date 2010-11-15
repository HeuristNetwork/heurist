<?php

/* Find any records which are *exactly the same* as another record */

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

if (! is_admin()) return;


mysql_connection_localhost_overwrite(DATABASE);


/* Necessary but insufficient condition is for the rec_hhash to be the same */

$bibIDs = mysql__select_array("records", "group_concat(rec_id), count(rec_id) C", "1 group by rec_hhash having C > 1");
print mysql_error();

$res = mysql_query("select A.rec_hhash, A.rec_id, B.rec_id, count(BB.rd_id) as C
                      from records A left join rec_details AA on AA.rd_rec_id = A.rec_id,
                           records B left join rec_details BB on BB.rd_rec_id = B.rec_id
                     where AA.rd_type = BB.rd_type and AA.rd_val = BB.rd_val and A.rec_hhash = B.rec_hhash
		       and A.rec_id in (" . join(',', $bibIDs) . ")
                       and A.rec_replaced_by_rec_id is null and B.rec_replaced_by_rec_id is null
                       and (A.rec_url = B.rec_url or (A.rec_url is null and B.rec_url is null))
                       and (A.rec_title = B.rec_title)
                  group by A.rec_id, B.rec_id order by B.rec_hhash, C desc");

$prev_hhash = NULL;
$prev_count = 0;
$bibs = array();
print mysql_error() . "\n";

?>
<style>
td { text-align: center; }
td:first-child { text-align: right; }
</style>
<?php

mysql_query("start transaction");

print "<table><tr><th>master bib ID</th><th>#records</th><th>#references</th><th>#bkmk</th><th>#kwd</th><th>#reminders</th><th>errors</th></tr>";
while ($bib = mysql_fetch_row($res)) {
	if ($prev_hhash === $bib[0]) {
		// same hhash and count as previous entry; start grouping
		if ($prev_count === $bib[3]) $bibs[$bib[2]] = $bib[2];
	}
	else {
		if (count($bibs) > 1) {
			do_fix_dupe($bibs);
			// print join(",", array_keys($bibs)) . "\n";
		}

		$prev_hhash = $bib[0];
		$prev_count = $bib[3];
		$bibs = array($bib[1] => $bib[1]);
	}
}
if (count($bibs) > 1) {
	do_fix_dupe($bibs);
}
print "</table>";

mysql_query("commit");


function do_fix_dupe($bibIDs) {
	/*
	 * $bibIDs is an array of IDs of records records that are all exactly the SAME.
	 * We try to identify which of the records is the most important, keep that as the master,
	 * and retrofit all pointers to the other records to point at that one.
	 */
	$bibIDlist = join(",", $bibIDs);
	$mostPopular = mysql_fetch_row(mysql_query("select rec_id from records where rec_id in ($bibIDlist) order by rec_popularity desc limit 1"));
		$mostPopular = $mostPopular[0];

	// remove mostPopular from the bibID list
	$bibIDlist = preg_replace("/\\b$mostPopular,|,$mostPopular\\b/", "", $bibIDlist);
	$masterBibID = $mostPopular;

		$errors = "";
	mysql_query("update records set rec_modified=now(), rec_replaced_by_rec_id=$masterBibID where rec_id in ($bibIDlist)");
        $bibCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';

        mysql_query("update rec_details left join rec_detail_types on rdt_id=rd_type
                                             set rd_val=$masterBibID
                                           where rd_val in ($bibIDlist) and rdt_type='resource'");
	$bdCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';

        mysql_query("update ignore usrBookmarks set bkm_recID=$masterBibID where bkm_recID in ($bibIDlist)");
	$bkmkCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';
        mysql_query("update ignore usrRecTagLinks set rtl_RecID=$masterBibID where rtl_RecID in ($bibIDlist)");
	$kwiCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';
        mysql_query("update ignore reminders set rem_rec_id=$masterBibID where rem_rec_id in ($bibIDlist)");
	$remCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';

	print "<!-- $bibIDlist -->\n";
	print "<tr><td><b>$masterBibID</b></td><td>$bibCount</td><td>$bdCount</td><td>$bkmkCount</td><td>$kwiCount</td><td>$remCount</td><td>$errors</td></tr>\n";
}

?>
