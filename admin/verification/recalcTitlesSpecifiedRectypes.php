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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

if (! is_admin()) {
?>
You must be a HEURIST administrator to use this page.
<?php
	return;
}
if(@$_REQUEST['recTypeIDs']) {
 $recTypeIds = $_REQUEST['recTypeIDs'];
}else{
?>
You must specify a record type (?recTypeIDs=55) a set of record types (?recTypeIDs=55,174,175) to use this page.
<?php
}

require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');


mysql_connection_db_overwrite(DATABASE);


$res = mysql_query("select rec_ID, rec_Title, rec_RecTypeID from Records where ! rec_FlagTemporary and rec_RecTypeID in ($recTypeIds) order by rand()");
$bibs = array();
while ($row = mysql_fetch_assoc($res)) {
	$bibs[$row['rec_ID']] = $row;
}

$masks = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_TitleMask', '1');
$updates = array();
$blank_count = 0;
$repair_count = 0;
$processed_count = 0;

//print '<style type="text/css">b span { color: red; }</style>';
//print '<style type="text/css">li.same, li.same * { color: lightgray; }</style>';
//print '<ul>';

?>
<h2>COMPOSITE RECORD RECALCULATION</h2>

<p>
   This function recalculates the constructed record titles for the given
   recordset, compares them with the existing title and updates the title
   where the title has changed. At the end of the process it will display
   a list of records for which the titles were changed and a list of
   records for which the new title would be blank (an error condition).
</p>


<script type="text/javascript">
function update_counts(processed, blank, repair, changed) {
	document.getElementById('processed_count').innerHTML = processed;
	document.getElementById('changed_count').innerHTML = changed;
	document.getElementById('same_count').innerHTML = processed - (changed + blank);
	document.getElementById('repair_count').innerHTML = repair;
	document.getElementById('blank_count').innerHTML = blank;
	document.getElementById('percent').innerHTML = Math.round(1000 * processed / <?= count($bibs) ?>) / 10;
}

function update_counts2(processed, total) {
	document.getElementById('updated_count').innerHTML = processed;
	document.getElementById('percent2').innerHTML = Math.round(1000 * processed / total) / 10;
}
</script>
<?php

print '<div><span id=total_count>'.count($bibs).'</span> records in total</div>';
print '<div><span id=processed_count>0</span> processed so far (<span id=percent>0</span>% done)</div>';
print '<div><span id=changed_count>0</span> to be updated</div>';
print '<div><span id=same_count>0</span> are the same</div>';
print '<div><span id=repair_count>0</span> are internet bookmarks that are reparable</div>';
print '<div><span id=blank_count>0</span> to be left as is (missing fields etc)</div>';

$blanks = array();
$reparables = array();
foreach ($bibs as $rec_id => $bib) {
	if ($rec_id % 10 == 0) {
		print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','.$repair_count.','.count($updates).')</script>'."\n";
		ob_flush();
		flush();
	}

	$mask = $masks[$bib['rec_RecTypeID']];
	$new_title = trim(fill_title_mask($mask, $rec_id, $bib['rec_RecTypeID']));
	++$processed_count;
	$bib_title = trim($bib['rec_Title']);
	if ($new_title && $bib_title && $new_title == $bib_title && strstr($new_title, $bib_title) )  continue;

	if (! preg_match('/^\\s*$/', $new_title)) {	// if new title is blank, leave the existing title
		$updates[$rec_id] = $new_title;
	}else {
		if ( $rec['rec_RecTypeID'] == 1 && $rec['rec_Title']) {
			array_push($reparables, $rec_id);
			++$repair_count;
		}else{
			array_push($blanks, $rec_id);
			++$blank_count;
		}
	}
	continue;
/*
	if (substr($new_title, 0, strlen($bib['rec_Title']) == $bib['rec_Title']))
		print '<li><b>' . htmlspecialchars($bib['rec_Title']) . '<span>' . htmlspecialchars(substr($new_title, strlen($bib['rec_Title']))) . '</span>' . '</b> [' . $rec_id . ': ' . htmlspecialchars($bib['rec_Title']) . ']';
	else
		print '<li><b>' . htmlspecialchars($new_title) . '</b> [' . $rec_id . ': ' . htmlspecialchars($bib['rec_Title']) . ']';
*/
	if ($new_title == preg_replace('/\\s+/', ' ', $bib['rec_Title']))
		print '<li class=same>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($bib['rec_Title']) . '';
	else
		print '<li>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($bib['rec_Title']) . '';

	print ' <a target=_blank href="'.HEURIST_URL_BASE.'records/edit/editRecord.html?bib_id='.$rec_id.'&db='.HEURIST_DBNAME.'">*</a> <br> <br>';

	if ($rec_id % 10 == 0) {
		ob_flush();
		flush();
	}
}
//print '</ul>';


print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','.count($updates).')</script>'."\n";
print '<hr>';

if (count($updates) > 0) {

	print '<p>Updating records</p>';
	print '<div><span id=updated_count>0</span> of '.count($updates).' records updated (<span id=percent2>0</span>%)</div>';

	$i = 0;
	foreach ($updates as $rec_id => $new_title) {
/*
		mysql_query('update Records set rec_Modified=now(), rec_Title="'.addslashes($new_title).'" where rec_ID='.$rec_id.' and rec_Title!="'.addslashes($new_title).'"');
*/
		mysql_query('update Records set rec_Title="'.addslashes($new_title).'" where rec_ID='.$rec_id);
		++$i;
		if ($rec_id % 10 == 0) {
			print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";
			ob_flush();
			flush();
		}
	}
	foreach ($reparables as $rec_id) {
		$rec = $bibs[$rec_id];
		if ( $rec['rec_RecTypeID'] == 1 && $rec['rec_Title']) {
			$has_detail_160 = (mysql_num_rows(mysql_query('select dtl_ID from recDetails where dtl_DetailTypeID = 160 and dtl_RecID ='. $rec_id)) > 0);
			//touch the record so we can update it  (required by the heuristdb triggers)
			mysql_query('update Records set rec_RecTypeID=1 where rec_ID='.$rec_id);
			if ($has_detail_160) {
				mysql_query('update recDetails set dtl_Value="' .$rec['rec_Title'] . '" where dtl_DetailTypeID = 160 and dtl_RecID='.$rec_id);
			}else{
				mysql_query('insert into recDetails (dtl_RecID, dtl_Value) VALUES(' .$rec_id . ','.$rec['rec_Title'] . ')');
			}
		}
	}
	print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";

	print '<hr>';

	print '<a target=_blank href="'.HEURIST_URL_BASE.'search/search.html?w=all&q=ids:'.join(',', array_keys($updates)).'&db='.HEURIST_DBNAME.'">Updated records</a><br>';
}
print '<a target=_blank href="'.HEURIST_URL_BASE.'search/search.html?w=all&q=ids:'.join(',', $blanks).'&db='.HEURIST_DBNAME.'">Unchanged records (title would be blank)</a>';

ob_flush();
flush();

?>
