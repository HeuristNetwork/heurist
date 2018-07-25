<?php

/*
* Copyright (C) 2005-2018 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2018 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* Find any records which are *exactly the same* as another record */

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_admin()) return;


mysql_connection_overwrite(DATABASE);


/* Necessary but insufficient condition is for the rec_Hash to be the same */

$bibIDs = mysql__select_array("Records", "group_concat(rec_ID), count(rec_ID) C", "1 group by rec_Hash having C > 1");
print mysql_error();

$res = mysql_query("select A.rec_Hash, A.rec_ID, B.rec_ID, count(BB.dtl_ID) as C
                      from Records A left join recDetails AA on AA.dtl_RecID = A.rec_ID,
                           Records B left join recDetails BB on BB.dtl_RecID = B.rec_ID
                     where AA.dtl_DetailTypeID = BB.dtl_DetailTypeID and AA.dtl_Value = BB.dtl_Value and A.rec_Hash = B.rec_Hash
		       and A.rec_ID in (" . join(',', $bibIDs) . ")
                       and A.rec_replaced_by_rec_id is null and B.rec_replaced_by_rec_id is null
                       and (A.rec_URL = B.rec_URL or (A.rec_URL is null and B.rec_URL is null))
                       and (A.rec_Title = B.rec_Title)
                  group by A.rec_ID, B.rec_ID order by B.rec_Hash, C desc");

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
	$mostPopular = mysql_fetch_row(mysql_query("select rec_ID from Records where rec_ID in ($bibIDlist) order by rec_Popularity desc limit 1"));
		$mostPopular = $mostPopular[0];

	// remove mostPopular from the bibID list
	$bibIDlist = preg_replace("/\\b$mostPopular,|,$mostPopular\\b/", "", $bibIDlist);
	$masterBibID = $mostPopular;

		$errors = "";
	mysql_query("update Records set rec_Modified=now(), rec_replaced_by_rec_id=$masterBibID where rec_ID in ($bibIDlist)");
        $bibCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';

        mysql_query("update recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID
                                             set dtl_Value=$masterBibID
                                           where dtl_Value in ($bibIDlist) and dty_Type='resource'");
	$bdCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';

        mysql_query("update ignore usrBookmarks set bkm_recID=$masterBibID where bkm_recID in ($bibIDlist)");
	$bkmkCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';
        mysql_query("update ignore usrRecTagLinks set rtl_RecID=$masterBibID where rtl_RecID in ($bibIDlist)");
	$kwiCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';
        mysql_query("update ignore usrReminders set rem_RecID=$masterBibID where rem_RecID in ($bibIDlist)");
	$remCount = mysql_affected_rows();
		$errors .= mysql_error() . ' ';

	print "<!-- $bibIDlist -->\n";
	print "<tr><td><b>$masterBibID</b></td><td>$bibCount</td><td>$bdCount</td><td>$bkmkCount</td><td>$kwiCount</td><td>$remCount</td><td>$errors</td></tr>\n";
}

?>
