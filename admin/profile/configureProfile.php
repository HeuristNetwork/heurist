<?php

/**
 * manageTags.php
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/t1000/.ht_stdefs');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

$tag_message = '';
if (@$_REQUEST['delete_kwd_id']) {
	mysql_connection_db_overwrite(DATABASE);
	$kwd_id = intval(@$_REQUEST['delete_kwd_id']);
	mysql_query('delete from usrTags where tag_ID = ' . $kwd_id . ' and tag_UGrpID= ' . get_user_id());
	if (mysql_affected_rows()) {
		mysql_query('delete from usrRecTagLinks where rtl_TagID = ' . $kwd_id);
		$tag_message .= '<div class="success">Tag was deleted</div>';
	} else {
		$tag_message .= '<div class="failure">Tag was not deleted</div>';
	}
}

if (@$_REQUEST['update_kwd_from']  and  @$_REQUEST['update_kwd_to']) {
	mysql_connection_db_overwrite(DATABASE);
	$kwd_from = intval(@$_REQUEST['update_kwd_from']);
	$kwd_to = intval(@$_REQUEST['update_kwd_to']);

	/* check that both tags belong to this user */
	$res = mysql_query('select * from usrTags where tag_ID in ('.$kwd_from.','.$kwd_to.') and tag_UGrpID='.get_user_id());
	if (mysql_num_rows($res) == 2) {
		mysql_query('update ignore usrRecTagLinks set rtl_TagID = '.$kwd_to.' where rtl_TagID = '.$kwd_from);
		$count = mysql_affected_rows();
		mysql_query('delete from usrTags where tag_ID = '.$kwd_from);

		if ($count == -1)
			$tag_message .= '<div class="success">Tag changed: duplicate tag links removed</div>';
		else if ($count != 1)
			$tag_message .= '<div class="success">Tag changed: ' . $count . ' tag links updated</div>';
		else
			$tag_message .= '<div class="success">Tag changed: one tag link updated</div>';
	} else {
		$tag_message .= '<div class="failure">Tag not changed</div>';
	}
}

if (@$_REQUEST['change_names']) {
	mysql_connection_db_overwrite(DATABASE);
	$orig_kwd_label = mysql__select_assoc('usrTags', 'tag_ID', 'tag_Text', 'tag_UGrpID='.get_user_id());

	$count = 0;
	foreach (@$_REQUEST['kwdl'] as $kwd_id => $new_kwd_label) {
		if ($orig_kwd_label[$kwd_id]  and  $orig_kwd_label[$kwd_id] != $new_kwd_label) {
			mysql_query('update usrTags set tag_Text="'.addslashes($new_kwd_label).'"
			                           where tag_ID='.intval($kwd_id));
			$count += mysql_affected_rows();
		}
	}
	if ($count > 1)
		$tag_message .= '<div class="success">'.$count.' tags renamed</div>';
	else if ($count == 1)
		$tag_message .= '<div class="success">One tag renamed</div>';
	else
		$tag_message .= '<div class="failure">Error of some sort: ' . mysql_error() . '</div>';
}

if (@$_REQUEST['replace_kwd']) {
	mysql_connection_db_overwrite(DATABASE);
	mysql_query('update usrRecTagLinks set rtl_TagID = '.intval(@$_REQUEST['replace_with_kwd_id']).' where rtl_TagID = '.intval($_REQUEST['replace_kwd_id']));
	$tag_message .= '<div class="success">Tag replaced</div>';
}

if (@$_REQUEST['delete_multiple_kwds']) {
	$kwd_ids = array_map('intval', array_keys($_REQUEST['delete_kwds']));
	if (count($kwd_ids)) {
		mysql_connection_db_overwrite(DATABASE);
		$res = mysql_query('delete usrTags, usrRecTagLinks from usrTags left join usrRecTagLinks on rtl_TagID = tag_ID where tag_ID in ('. join(', ', $kwd_ids) .') and tag_UGrpID='.get_user_id());
		$tag_message .= mysql_error() . '<div class="success">Tags deleted</div>';
	} else {
		$tag_message .= mysql_error() . '<div class="success">No tags deleted</div>';
	}
}


mysql_connection_db_select(DATABASE);


/* Specify the template file containing the web page to be processed and displayed */
$template = file_get_contents('configureProfile.html');

$template = str_replace('{database}', HEURIST_DBNAME, $template);

if (@$_REQUEST['tag_edit'])
	$template = str_replace('<body ', '<body class=tag_edit ', $template);

$template = str_replace('{tag_edit}', @$_REQUEST['tag_edit'], $template);

$template = str_replace('{body_only}', (array_key_exists('body_only', $_REQUEST)? '<input type=hidden name=body_only>' : ''), $template);
$template = str_replace('{section}', @$_REQUEST['section'], $template);


$res = mysql_query('select count(rtl_ID) as cnt from usrTags left join usrRecTagLinks on rtl_TagID=tag_ID where tag_UGrpID= ' . get_user_id() . ' group by tag_ID order by cnt desc, tag_Text limit 1');
$row = mysql_fetch_row($res);
$max_cnt = intval($row[0]);

if (@$_REQUEST['order_by_popularity']) {
	$res = mysql_query('select tag_ID, tag_Text, count(rtl_ID) as cnt from usrTags left join usrRecTagLinks on rtl_TagID=tag_ID where tag_UGrpID= ' . get_user_id() . ' group by tag_ID order by cnt desc, tag_Text');
} else {
	$res = mysql_query('select tag_ID, tag_Text, count(rtl_ID) as cnt from usrTags left join usrRecTagLinks on rtl_TagID=tag_ID where tag_UGrpID= ' . get_user_id() . ' group by tag_ID order by tag_Text');
}

$foreach_kwd = $foreach_kwd_js = '';
while ($row = mysql_fetch_row($res)) {
	$foreach_kwd .=
'<tr>
 <td><nobr>
  <input type="checkbox" style="vertical-align: middle;" name="delete_kwds['.$row[0].']">
  <img src="'.HEURIST_URL_BASE.'common/images/cross.gif" onclick="delete_kwd('.$row[0].',\''.htmlspecialchars($row[1]).'\','.$row[2].')">
  <input type="text" class="textinput" name="kwdl['.$row[0].']" value="'.htmlspecialchars($row[1]).'" onchange="rename_kwd('.$row[0].', this);">
 </nobr></td>
 <td class="count"><nobr>' . $row[2] . '</nobr></td>
 <td class="u-cell">
  <div class="u" title="' . $row[2] . ' records"><div style="width: ' . (intval($row[2]) / $max_cnt * 100) . '%;"></div></div>
 </td>
 <td class=search>'.($row[2] ? '<a title="Display records with this tag in a new search window" target=_blank href="'.HEURIST_URL_BASE.'search/search.html?w=bookmark&db='.HEURIST_DBNAME.'&q=tag:%22'.$row[1].'%22"><img src="'.HEURIST_URL_BASE.'common/images/jump.png"></a>': '').'</td>
 <td class=replace>'.($row[2] ? '<a href=# onclick="show_replace_list(this, '.$row[0].'); return false;">replace...</a>': '').'</td>
</tr>';

	$foreach_kwd_js .= "kwd['".htmlspecialchars(strtolower($row[1]))."'] = ".$row[0].";\n";
}

$kwd_select = "<select id=kwd_select style=\"display: none;\"><option value=\"\" disabled selected>select tag...</option>";
$res = mysql_query('select tag_ID, tag_Text from usrTags where tag_UGrpID= ' . get_user_id() . ' order by tag_Text');
while ($row = mysql_fetch_row($res)) {
	$kwd_select .= "<option value=".$row[0].">".htmlspecialchars($row[1])."</option>";
}
$kwd_select .= "</select>";

$sortby_button = @$_REQUEST['order_by_popularity']
	? '<input type="button" value="Sort alphabetically" onclick="document.getElementById(\'sortby_input\').value = \'\';" style="float: right;">'
	: '<input type="button" value="Sort by usage" onclick="document.getElementById(\'sortby_input\').value = \'order_by_popularity\';" style="float: right;">';
$sortby_input = @$_REQUEST['order_by_popularity']
	? '<input type="hidden" id="sortby_input" name="order_by_popularity" value="1">'
	: '<input type="hidden" id="sortby_input" name="order_by_popularity" value="">';


$template = str_replace('{ForeachTag}', $foreach_kwd, $template);
$template = str_replace('{ForeachTagJs}', $foreach_kwd_js, $template);
$template = str_replace('{TagMessage}', $tag_message, $template);
$template = str_replace('{UserHyperlinksImport}', $user_hyperlinks_import, $template);
$template = str_replace('{sortby_button}', $sortby_button, $template);
$template = str_replace('{sortby_input}', $sortby_input, $template);
$template = str_replace('{kwd_select}', $kwd_select, $template);

echo($template);

?>
