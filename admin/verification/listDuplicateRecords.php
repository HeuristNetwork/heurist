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

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_logged_in()  ||  ! is_admin()) return;


$fuzziness = intval($_REQUEST['fuzziness']);
if (! $fuzziness) $fuzziness = 10;

$bibs = array();
$dupes = array();
$dupekeys = array();
$recsGivenNames = array();
$dupeDifferences = array();

mysql_connection_db_insert(DATABASE);


$res = mysql_query('select snd_SimRecsList from recSimilarButNotDupes');
while ($row = mysql_fetch_assoc($res)){
	array_push($dupeDifferences,$row['snd_SimRecsList']);
}

if ($_REQUEST['dupeDiffHash']){
	foreach($_REQUEST['dupeDiffHash'] as $diffHash){
		if (! in_array($diffHash,$dupeDifferences)){
			array_push($dupeDifferences,$diffHash);
			$res = mysql_query('insert into recSimilarButNotDupes values("'.$diffHash.'")');
		}
	}
}

mysql_connection_db_select(DATABASE);
//mysql_connection_db_select("`heuristdb-nyirti`");   //for debug
//FIXME  allow user to select a single record type
//$res = mysql_query('select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=160 where rec_RecTypeID != 52 and rec_RecTypeID != 55 and not rec_FlagTemporary order by rec_RecTypeID desc');

$crosstype = false;
$personMatch = false;

if (@$_REQUEST['crosstype']){
	$crosstype = true;
}
if (@$_REQUEST['personmatch']){
    $personMatch = true;
    $res = mysql_query('select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=291 where rec_RecTypeID = 55 and not rec_FlagTemporary order by rec_ID desc');    //Given Name
    while ($row = mysql_fetch_assoc($res)) {
       $recsGivenNames[$row['rec_ID']] = $row['dtl_Value'];
    }
    $res = mysql_query('select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=160 where rec_RecTypeID = 55 and not rec_FlagTemporary order by dtl_Value asc');    //Family Name

} else{
    $res = mysql_query('select rec_ID, rec_RecTypeID, rec_Title, dtl_Value from Records left join recDetails on dtl_RecID=rec_ID and dtl_DetailTypeID=160 where rec_RecTypeID != 52 and not rec_FlagTemporary order by rec_RecTypeID desc');
}

$rectypes = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_Name', '1');

while ($row = mysql_fetch_assoc($res)) {
    if ($personMatch){
       if($row['dtl_Value']) $val = $row['dtl_Value'] . ($recsGivenNames[$row['rec_ID']]? " ". $recsGivenNames[$row['rec_ID']]: "" );
    }else {
	    if ($row['rec_Title']) $val = $row['rec_Title'];
	    else $val = $row['dtl_Value'];
    }
	$mval = metaphone(preg_replace('/^(?:a|an|the|la|il|le|die|i|les|un|der|gli|das|zur|una|ein|eine|lo|une)\\s+|^l\'\\b/i', '', $val));

	if ($crosstype || $personMatch) { //for crosstype or person matching leave off the type ID
      $key = ''.substr($mval, 0, $fuzziness);
    } else {
      $key = $row['rec_RecTypeID'] . '.' . substr($mval, 0, $fuzziness);
    }

    $typekey = $rectypes[$row['rec_RecTypeID']];

	if (! array_key_exists($key, $bibs)) $bibs[$key] = array(); //if the key doesn't exist then make an entry for this metaphone
	else { // it's a dupe so process it
        if (! array_key_exists($typekey, $dupes)) $dupes[$typekey] = array();
        if (!array_key_exists($key,$dupekeys))  {
            $dupekeys[$key] =  1;
            array_push($dupes[$typekey],$key);
        }
    }
	// add the record to bibs
	$bibs[$key][$row['rec_ID']] = array('type' => $typekey, 'val' => $val);
}

ksort($dupes);
foreach ($dupes as $typekey => $subarr) {
    array_multisort($dupes[$typekey],SORT_ASC,SORT_STRING);
}

?><html>
<body>
<form>
Select fuzziness: <select name="fuzziness" id="fuzziness" onchange="form.submit();">
<option value=3>3</option>
<option value=4 <?= $fuzziness == 4  ? "selected" : "" ?>>4</option>
<option value=5 <?= $fuzziness == 5 ? "selected" : "" ?>>5</option>
<option value=6 <?= $fuzziness == 6 ? "selected" : "" ?>>6</option>
<option value=7 <?= $fuzziness == 7 ? "selected" : "" ?>>7</option>
<option value=8 <?= $fuzziness == 8 ? "selected" : "" ?>>8</option>
<option value=9 <?= $fuzziness == 9 ? "selected" : "" ?>>9</option>
<option value=10 <?= $fuzziness >= 10 && $fuzziness < 12 ? "selected" : "" ?>>10</option>
<option value=12 <?= $fuzziness >= 12 && $fuzziness < 15 ? "selected" : "" ?>>12</option>
<option value=15 <?= $fuzziness >= 15 && $fuzziness < 20 ? "selected" : "" ?>>15</option>
<option value=20 <?= $fuzziness >= 20 && $fuzziness < 25 ? "selected" : "" ?>>20</option>
<option value=25 <?= $fuzziness >= 25 && $fuzziness < 30 ? "selected" : "" ?>>25</option>
<option value=30 <?= $fuzziness >= 30 ? "selected" : "" ?>>30</option>
</select>
characters of metaphone must match
<br />
<br />Cross type matching will attemp to match titles of different record types. This is potentially a long search
<br />with many matching results. Increasing fuzziness will reduce the number of matches.
<br />
<br />
<input type="checkbox" name="crosstype" id="crosstype" value=1 <?= $crosstype ? "checked" : "" ?>  onclick="form.submit();"> Do Cross Type Matching
<br />
<input type="checkbox" name="personmatch" id="personmatch" value=1   onclick="form.submit();"> Do Person Matching by SurName first

<?php
  unset($_REQUEST['personmatch']);

print '<div>' . count($dupes) . ' potential groups of duplicates</div><hr>';

foreach ($dupes as $rectype => $subarr) {
    foreach ($subarr as $index => $key) {
    	$diffHash = array_keys($bibs[$key]);
    	sort($diffHash,SORT_ASC);
    	$diffHash = join(',',$diffHash );
    	if (in_array($diffHash,$dupeDifferences)) continue;
	    print '<div style="font-weight: bold;">' . $rectype . '&nbsp;&nbsp;&nbsp;&nbsp;';
	    print '<a target="_new" href="'.HEURIST_URL_BASE.'search/search.html?db='.HEURIST_DBNAME.'&w=all&q=ids:' . join(',', array_keys($bibs[$key])) . '">search</a>&nbsp;&nbsp;&nbsp;&nbsp;';
	    print '<a target="fix" href="combineDuplicateRecords.php?db='.HEURIST_DBNAME.'&bib_ids=' . join(',', array_keys($bibs[$key])) . '">fix</a>&nbsp;&nbsp;&nbsp;&nbsp;';
	    print '<input type="checkbox" name="dupeDiffHash[] title="Check to idicate that all records in this set are unique." id="'.$key.
	    		'" value="' . $diffHash . '">&nbsp;&nbsp;';
	    print '<input type="submit" value="hide">';
	    print '</div>';
	    print '<ul>';
	    foreach ($bibs[$key] as $rec_id => $vals) {
		    $res = mysql_query('select rec_URL from Records where rec_ID = ' . $rec_id);
		    $row = mysql_fetch_assoc($res);
		    print '<li>'.($crosstype ? $vals['type'].'&nbsp;&nbsp;' : '').
		    		'<a target="_new" href="'.HEURIST_URL_BASE.'records/view/viewRecord.php?db='.HEURIST_DBNAME.'&saneopen=1&bib_id='.$rec_id.'">'.$rec_id.': '.htmlspecialchars($vals['val']).'</a>';
		    if ($row['rec_URL'])
			    print '&nbsp;&nbsp;&nbsp;<span style="font-size: 70%;">(<a target="_new" href="'.$row['rec_URL'].'">' . $row['rec_URL'] . '</a>)</span>';
		    print '</li>';
	    }
	    print '</ul>';
    }
}
?>
</form>
</body>
</html>
