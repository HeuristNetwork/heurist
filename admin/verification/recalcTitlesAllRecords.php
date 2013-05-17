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
* recalcTitlesAllRecords.php
* Rebuilds the constructed record titles listed in search results, for all records
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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    if(isForAdminOnly("to rebuild titles")){
        return;
    }

mysql_connection_overwrite(DATABASE);

require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php'); //?db='.HEURIST_DBNAME);

$res = mysql_query('select rec_ID, rec_Title, rec_RecTypeID from Records where ! rec_FlagTemporary order by rand()');
$recs = array();
while ($row = mysql_fetch_assoc($res)) {
	$recs[$row['rec_ID']] = $row;
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
<html>
<head>
<script type="text/javascript">
function update_counts(processed, blank, repair, changed) {
	if(changed==null || changed==undefined){
		changed = 0;
	}
	document.getElementById('processed_count').innerHTML = processed;
	document.getElementById('changed_count').innerHTML = changed;
	document.getElementById('same_count').innerHTML = processed - (changed + blank);
	document.getElementById('repair_count').innerHTML = repair;
	document.getElementById('blank_count').innerHTML = blank;
	document.getElementById('percent').innerHTML = Math.round(1000 * processed / <?= count($recs) ?>) / 10;
}

function update_counts2(processed, total) {
	document.getElementById('updated_count').innerHTML = processed;
	document.getElementById('percent2').innerHTML = Math.round(1000 * processed / total) / 10;
}
</script>

    	<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
    	<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
</head>
<body class="popup">

<div class="banner"><h2>Rebuild Titles</h2></div>
<div id="page-inner" style="overflow:auto;padding: 20px;">

<div style="max-width: 500px;">
   This function recalculates all the constructed record titles, compares
   them with the existing title and updates the title where the title has
   changed. At the end of the process it will display a list of records
   for which the titles were changed and a list of records for which the
   new title would be blank (an error condition).
</div>
<p>This will take some time for large databases.</p>
<!-- <p>The scanning step does not write to the database and can be cancelled safely at any time</p> -->

<div><span id=total_count><?=count($recs)?></span> records in total</div>
<div><span id=processed_count>0</span> processed so far</div>
<div><span id=percent>0</span> %</div>
<br />
<div><span id=changed_count>0</span> to be updated</div>
<div><span id=same_count>0</span> are unchanged</div>
<div><span id=repair_count>0</span> marked for update</div>
<div><span id=blank_count>0</span> will be left as-is (missing fields etc)</div>

<?php
/*
print '<div><span id=total_count>'.count($recs).'</span> records in total</div>';
print '<div><span id=processed_count>0</span> processed so far (<span id=percent>0</span>% done)</div>';
print '<div><span id=changed_count>0</span> to be updated</div>';
print '<div><span id=same_count>0</span> are the same</div>';
print '<div><span id=repair_count>0</span> are internet bookmarks that are reparable</div>';
print '<div><span id=blank_count>0</span> to be left as is (missing fields etc)</div>';
		ob_flush();
		flush();
*/
$blanks = array();
$reparables = array();
foreach ($recs as $rec_id => $rec) {
	if ($rec_id % 10 == 0) {
/*****DEBUG****///error_log(">>>>".$processed_count.','.$blank_count.','.$repair_count.','.count($updates));

		print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','.$repair_count.','.count($updates).')</script>'."\n";
		ob_flush();
		flush();
	}

	$mask = $masks[$rec['rec_RecTypeID']];
	$new_title = trim(fill_title_mask($mask, $rec_id, $rec['rec_RecTypeID']));
	++$processed_count;
	$rec_title = trim($rec['rec_Title']);
	if ($new_title && $rec_title && $new_title == $rec_title && strstr($new_title, $rec_title) )  continue;

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
	if (substr($new_title, 0, strlen($rec['rec_Title']) == $rec['rec_Title']))
		print '<li><b>' . htmlspecialchars($rec['rec_Title']) . '<span>' . htmlspecialchars(substr($new_title, strlen($rec['rec_Title']))) . '</span>' . '</b> [' . $rec_id . ': ' . htmlspecialchars($rec['rec_Title']) . ']';
	else
		print '<li><b>' . htmlspecialchars($new_title) . '</b> [' . $rec_id . ': ' . htmlspecialchars($rec['rec_Title']) . ']';
*/
	if ($new_title == preg_replace('/\\s+/', ' ', $rec['rec_Title']))
		print '<li class=same>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($rec['rec_Title']) . '';
	else
		print '<li>' . htmlspecialchars($new_title) . '<br>'  . htmlspecialchars($rec['rec_Title']) . '';

	print ' <a target=_blank href="'.HEURIST_BASE_URL.'records/edit/editRecord.html?db='.HEURIST_DBNAME.'&recID='.$rec_id.'">*</a> <br> <br>';

	if ($rec_id % 10 == 0) {
		ob_flush();
		flush();
	}
}//for
//print '</ul>';


print '<script type="text/javascript">update_counts('.$processed_count.','.$blank_count.','.$repair_count.','.count($updates).')</script>'."\n";
print '<hr>';
$titleDT = (defined('DT_NAME')?DT_NAME:0);

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
		$rec = $recs[$rec_id];
		if ( $rec['rec_RecTypeID'] == 1 && $rec['rec_Title']) {
			$has_detail_160 = (mysql_num_rows(mysql_query("select dtl_ID from recDetails where dtl_DetailTypeID = $titleDT and dtl_RecID =". $rec_id)) > 0);
			//touch the record so we can update it  (required by the heuristdb triggers)
			mysql_query('update Records set rec_RecTypeID=1 where rec_ID='.$rec_id);
			if ($has_detail_160) {
				mysql_query('update recDetails set dtl_Value="' .$rec['rec_Title'] . "\" where dtl_DetailTypeID = $titleDT and dtl_RecID=".$rec_id);
			}else{
				mysql_query('insert into recDetails (dtl_RecID, dtl_Value) VALUES(' .$rec_id . ','.$rec['rec_Title'] . ')');
			}
		}
	}
	print '<script type="text/javascript">update_counts2('.$i.','.count($updates).')</script>'."\n";

	print '<hr>';

	print '<a target=_blank href="'.HEURIST_BASE_URL.'search/search.html?db='.HEURIST_DBNAME.'&w=all&q=ids:'.join(',', array_keys($updates)).'">Updated records</a><br>';
}
if(count($blanks)>0){
	print '<a target=_blank href="'.HEURIST_BASE_URL.'search/search.html?db='.HEURIST_DBNAME.'&w=all&q=ids:'.join(',', $blanks).'">Unchanged records (title would be blank)</a>';
}

ob_flush();
flush();

?>
</div>
</body>
</html>