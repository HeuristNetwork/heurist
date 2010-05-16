<?php

require_once('../php/modules/db.php');
require_once('../php/modules/cred.php');

if (! is_logged_in()) {
	header('Location: ' . BASE_PATH . 'php/login.php');
	return;
}

if (! is_admin()) {
?>
You must be a SHSSERI administrator to use this page.
<?php
	return;
}

require_once('../php/modules/TitleMask.php');


mysql_connection_db_overwrite(DATABASE);


$res = mysql_query('select rec_id, rec_title, rec_type from records where ! rec_temporary order by rand()');
$bibs = array();
while ($row = mysql_fetch_assoc($res)) {
	$bibs[$row['rec_id']] = $row;
}


$masks = mysql__select_assoc('rec_types', 'rt_id', 'rt_title_mask', '1');
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
   This function recalculates all the constructed record titles, compares
   them with the existing title and updates the title where the title has
   changed. At the end of the process it will display a list of records
   for which the titles were changed and a list of records for which the
   new title would be blank (an error condition).
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

	$mask = $masks[$bib['rec_type']];
	$new_title = trim(fill_title_mask($mask, $rec_id, $bib['rec_type']));
	++$processed_count;
	$bib_title = trim($bib['rec_title']);
	if ($new_title && $bib_title && $new_title == $bib_title && strstr($new_title, $bib_title) )  continue;

	if (! preg_match('/^\\s*$/', $new_title)) {	// if new title is blank, leave the existing title
		$updates[$rec_id] = $new_title;
	}else {
		if ( $rec['rec_type'] == 1 && $rec['rec_title']) {
			array_push($reparables, $rec_id);
			++$repair_count;
		}else{
			array_push($blanks, $rec_id);
			++$blank_count;
		}
	}
	continue;

/*
	if (substr($new_title, 0, strlen($bib['rec_title']) == $bib['rec_title']))
		print '<li><b>' . htmlspecialchars($bib['rec_title']) . '<span>' . htmlspecialchars(substr($new_title, strlen($bib['rec_title']))) . '</span>' . '</b> [' . $rec_id . ': ' . htmlspecialchars($bib['rec_title']) . ']';
	else
		print '<li><b>' . htmlspecialchars($new_title) . '</b> [' . $rec_id . ': ' . htmlspecialchars($bib['rec_title']) . ']';
*/
	if ($new_title == preg_replace('/\\s+/', ' ', $bib['rec_title']))
		print '<li class=same>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($bib['rec_title']) . '';
	else
		print '<li>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($bib['rec_title']) . '';

	print ' <a target=_blank href="../edit?bib_id='.$rec_id.'">*</a> <br> <br>';

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
		mysql_query('update records set rec_modified=now(), rec_title="'.addslashes($new_title).'" where rec_id='.$rec_id.' and rec_title!="'.addslashes($new_title).'"');
*/
		mysql_query('update records set rec_title="'.addslashes($new_title).'" where rec_id='.$rec_id);
		++$i;
		if ($rec_id % 10 == 0) {
			print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";
			ob_flush();
			flush();
		}
	}
	foreach ($reparables as $rec_id) {
		$rec = $bibs[$rec_id];
		if ( $rec['rec_type'] == 1 && $rec['rec_title']) {
			$has_detail_160 = (mysql_num_rows(mysql_query('select rd_id from rec_details where rd_type = 160 and rd_rec_id ='. $rec_id)) > 0);
			//touch the record so we can update it  (required by the heuristdb triggers)
			mysql_query('update records set rec_type=1 where rec_id='.$rec_id);
			if ($has_detail_160) {
				mysql_query('update rec_details set rd_val="' .$rec['rec_title'] . '" where rd_type = 160 and rd_rec_id='.$rec_id);
			}else{
				mysql_query('insert into rec_details (rd_rec_id, rd_val) VALUES(' .$rec_id . ','.$rec['rec_title'] . ')');
			}
		}
	}
	print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";

	print '<hr>';

	print '<a target=_blank href="../?w=all&q=ids:'.join(',', array_keys($updates)).'">Updated records</a><br>';
}
print '<a target=_blank href="../?w=all&q=ids:'.join(',', $blanks).'">Unchanged records (title would be blank)</a>';

ob_flush();
flush();

?>
