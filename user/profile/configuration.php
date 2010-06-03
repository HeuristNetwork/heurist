<?php

require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/.ht_stdefs');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}


if (@$_REQUEST['submitted']) {
	mysql_connection_db_overwrite(USERS_DATABASE);
	mysql_query('update Users set WordLimit = '.intval(@$_REQUEST['word_limit']).' where Id='.get_user_id());
	mysql_connection_db_overwrite(USERS_DATABASE);
	mysql_query('update Users set WordLimit = '.intval(@$_REQUEST['word_limit']).' where Id='.get_user_id());
	mysql_connection_db_overwrite(DATABASE);

	if (@$_REQUEST['new_hyp_text']) {
		$res = mysql_query('select * from ignored_hyperlink_texts
		                     where (hyp_usr_id is null or hyp_usr_id='.get_user_id().')
		                       and hyp_text="'.addslashes(@$_REQUEST['new_hyp_text']).'"');
		if (mysql_num_rows($res) == 0) {
			mysql__insert('ignored_hyperlink_texts',
			             array('hyp_text' => @$_REQUEST['new_hyp_text'],
			                   'hyp_usr_id' => get_user_id()));
		}
	}
}

$keyword_message = '';
if (@$_REQUEST['delete_kwd_id']) {
	mysql_connection_db_overwrite(DATABASE);
	$kwd_id = intval(@$_REQUEST['delete_kwd_id']);
	mysql_query('delete from keywords where kwd_id = ' . $kwd_id . ' and kwd_usr_id = ' . get_user_id());
	if (mysql_affected_rows()) {
		mysql_query('delete from keyword_links where kwl_kwd_id = ' . $kwd_id);
		$keyword_message .= '<div class="success">Tag was deleted</div>';
	} else {
		$keyword_message .= '<div class="failure">Tag was not deleted</div>';
	}
}
if (@$_REQUEST['update_kwd_from']  and  @$_REQUEST['update_kwd_to']) {
	mysql_connection_db_overwrite(DATABASE);
	$kwd_from = intval(@$_REQUEST['update_kwd_from']);
	$kwd_to = intval(@$_REQUEST['update_kwd_to']);

	/* check that both keywords belong to this user */
	$res = mysql_query('select * from keywords where kwd_id in ('.$kwd_from.','.$kwd_to.') and kwd_usr_id='.get_user_id());
	if (mysql_num_rows($res) == 2) {
		mysql_query('update ignore keyword_links set kwl_kwd_id = '.$kwd_to.' where kwl_kwd_id = '.$kwd_from);
		$count = mysql_affected_rows();
		mysql_query('delete from keywords where kwd_id = '.$kwd_from);

		if ($count == -1)
			$keyword_message .= '<div class="success">Tag changed: duplicate tag links removed</div>';
		else if ($count != 1)
			$keyword_message .= '<div class="success">Tag changed: ' . $count . ' tag links updated</div>';
		else
			$keyword_message .= '<div class="success">Tag changed: one tag link updated</div>';
	} else {
		$keyword_message .= '<div class="failure">Tag not changed</div>';
	}
}
if (@$_REQUEST['change_names']) {
	mysql_connection_db_overwrite(DATABASE);
	$orig_kwd_label = mysql__select_assoc('keywords', 'kwd_id', 'kwd_name', 'kwd_usr_id='.get_user_id());

	$count = 0;
	foreach (@$_REQUEST['kwdl'] as $kwd_id => $new_kwd_label) {
		if ($orig_kwd_label[$kwd_id]  and  $orig_kwd_label[$kwd_id] != $new_kwd_label) {
			mysql_query('update keywords set kwd_name="'.addslashes($new_kwd_label).'"
			                           where kwd_id='.intval($kwd_id));
			$count += mysql_affected_rows();
		}
	}
	if ($count > 1)
		$keyword_message .= '<div class="success">'.$count.' tags renamed</div>';
	else if ($count == 1)
		$keyword_message .= '<div class="success">One tag renamed</div>';
	else
		$keyword_message .= '<div class="failure">Error of some sort: ' . mysql_error() . '</div>';
}
if (@$_REQUEST['replace_kwd']) {
	mysql_connection_db_overwrite(DATABASE);
	mysql_query('update keyword_links set kwl_kwd_id = '.intval(@$_REQUEST['replace_with_kwd_id']).' where kwl_kwd_id = '.intval($_REQUEST['replace_kwd_id']));
	$keyword_message .= '<div class="success">Tag replaced</div>';
}
if (@$_REQUEST['delete_multiple_kwds']) {
	$kwd_ids = array_map('intval', array_keys($_REQUEST['delete_kwds']));
	if (count($kwd_ids)) {
		mysql_connection_db_overwrite(DATABASE);
		$res = mysql_query('delete keywords, keyword_links from keywords left join keyword_links on kwl_kwd_id = kwd_id where kwd_id in ('. join(', ', $kwd_ids) .') and kwd_usr_id='.get_user_id());
		$keyword_message .= mysql_error() . '<div class="success">Tags deleted</div>';
	} else {
		$keyword_message .= mysql_error() . '<div class="success">No tags deleted</div>';
	}
}

if (get_user_id() == 96) {
	mysql_connection_db_select(DATABASE);

	$user_hyperlinks_import = '<p>';
	if (@$_REQUEST['import_hyperlinks_user']) {
		$hls = mysql__select_array('ignored_hyperlink_texts', 'hyp_text',
		                           'hyp_usr_id='.intval(@$_REQUEST['import_hyperlinks_user']));
		if ($hls) {
			$insert_stmt = '';
			foreach ($hls as $hl) {
				if ($insert_stmt) $insert_stmt .= ', ';
				$insert_stmt .= '("'.addslashes($hl).'", get_user_id())';
			}
			$insert_stmt = 'insert into ignored_hyperlink_texts (hyp_text, hyp_usr_id) values ' . $insert_stmt;
			mysql_query($insert_stmt);
			$row_count = mysql_affected_rows();
		} else $row_count = 0;

		$user_hyperlinks_import .= '<span style="color: red; font-weight: bold;">';
		if ($row_count == 1)
			$user_hyperlinks_import .= 'One new hyperlink added.';
		else if ($row_count > 1)
			$user_hyperlinks_import .= $row_count . ' new hyperlinks added.';
		else
			$user_hyperlinks_import .= 'No new hyperlinks added.';
		$user_hyperlinks_import .= '</span>&nbsp;';
	}


	$user_hyperlinks_import .= '
  Import ignored hyperlinks from user:
  <select name="import_hyperlinks_user" onchange="form.submit();">
   <option value="">(select a user)</option>
';
	if (defined('HEURIST_USER_GROUP_ID')) {
		$usernames = mysql__select_assoc(USERS_DATABASE.'.Users left join '.USERS_DATABASE.'.UserGroups on ug_user_id=Id', 'Id', 'Username', 'ug_group_id='.HEURIST_USER_GROUP_ID.' and Id != 96 order by Username');
	} else {
		$usernames = mysql__select_assoc(USERS_DATABASE.'.Users', 'Id', 'Username', 'Id != 96 order by Username');
	}
	foreach ($usernames as $id => $name) {
		$user_hyperlinks_import .=
'   <option value="'.$id.'">'.htmlspecialchars($name).'</option>';
	}

	$user_hyperlinks_import .= '</select></p>';
END;

} else	$user_hyperlinks_import = '';

mysql_connection_db_select(DATABASE);


/* Specify the template file containing the web page to be processed and displayed */
$template = file_get_contents('configuration.html');

if (! array_key_exists('body_only', $_REQUEST)) {
	/* Replaces the word {PageHeader} in the web page with the concatenation of the files specified */

	$template = str_replace('{PageHeader}', file_get_contents(dirname(__FILE__).'/../../admin/describe/menu.html'), $template);
} else {
	$template = str_replace('{PageHeader}', '', $template);
	$template = str_replace('<body ', '<body width=600 height=650 ', $template);
}

if (@$_REQUEST['keyword_edit'])
	$template = str_replace('<body ', '<body class=keyword_edit ', $template);
else if (@$_REQUEST['bookmark_import'])
	$template = str_replace('<body ', '<body class=bookmark_import ', $template);
$template = str_replace('{keyword_edit}', @$_REQUEST['keyword_edit'], $template);
$template = str_replace('{bookmark_import}', @$_REQUEST['bookmark_import'], $template);
$template = str_replace('{body_only}', (array_key_exists('body_only', $_REQUEST)? '<input type=hidden name=body_only>' : ''), $template);

$template = str_replace('{section}', @$_REQUEST['section'], $template);

mysql_connection_db_select(USERS_DATABASE);
$res = mysql_query('select WordLimit from Users where Id = '.get_user_id());
$row = mysql_fetch_row($res);
$word_limit = $row[0];	// minimum number of spaces that must appear in the link text
mysql_connection_db_select(DATABASE);

$word_limit_options =
'<option value="0" '.($word_limit==0? 'selected':'').'>any number of words</option>' .
'<option value="1" '.($word_limit==1? 'selected':'').'>at least one word</option>' .
'<option value="2" '.($word_limit==2? 'selected':'').'>at least two words</option>' .
'<option value="3" '.($word_limit==3? 'selected':'').'>at least three words</option>' .
'<option value="4" '.($word_limit==4? 'selected':'').'>at least four words</option>' .
'<option value="5" '.($word_limit==5? 'selected':'').'>at least five words</option>';
$template = str_replace('{word_limit_options}', $word_limit_options, $template);

$hyperlinks_ignored = '<div>' .
  join("</div>\n<div>",
       mysql__select_array('ignored_hyperlink_texts', 'hyp_text', 'hyp_usr_id is null or hyp_usr_id='.get_user_id())) .
                      '</div>';
$template = str_replace('{hyperlinks_ignored}', $hyperlinks_ignored, $template);
$template = str_replace('{Bookmarklet}', file_get_contents('bookmarklet.js'), $template);

$res = mysql_query('select count(kwl_id) as cnt from keywords left join keyword_links on kwl_kwd_id=kwd_id where kwd_usr_id = ' . get_user_id() . ' group by kwd_id order by cnt desc, kwd_name limit 1');
$row = mysql_fetch_row($res);
$max_cnt = intval($row[0]);

if (@$_REQUEST['order_by_popularity']) {
	$res = mysql_query('select kwd_id, kwd_name, count(kwl_id) as cnt from keywords left join keyword_links on kwl_kwd_id=kwd_id where kwd_usr_id = ' . get_user_id() . ' group by kwd_id order by cnt desc, kwd_name');
} else {
	$res = mysql_query('select kwd_id, kwd_name, count(kwl_id) as cnt from keywords left join keyword_links on kwl_kwd_id=kwd_id where kwd_usr_id = ' . get_user_id() . ' group by kwd_id order by kwd_name');
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
 <td><nobr>' . $row[2] . '</nobr></td>
 <td class="u-cell">
  <div class="u" title="' . $row[2] . ' records"><div style="width: ' . (intval($row[2]) / $max_cnt * 100) . '%;"></div></div>
 </td>
 <td class=search>'.($row[2] ? '<a target=_blank href="'.dirname(__FILE__).'/../../search/heurist-search.html?w=bookmark&q=tag:%22'.$row[1].'%22">view...</a>': '').'</td>
 <td class=replace>'.($row[2] ? '<a href=# onclick="show_replace_list(this, '.$row[0].'); return false;">replace...</a>': '').'</td>
</tr>';

	$foreach_kwd_js .= "kwd['".htmlspecialchars(strtolower($row[1]))."'] = ".$row[0].";\n";
}

$kwd_select = "<select id=kwd_select style=\"display: none;\"><option value=\"\" disabled selected>select tag...</option>";
$res = mysql_query('select kwd_id, kwd_name from keywords where kwd_usr_id = ' . get_user_id() . ' order by kwd_name');
while ($row = mysql_fetch_row($res)) {
	$kwd_select .= "<option value=".$row[0].">".htmlspecialchars($row[1])."</option>";
}
$kwd_select .= "</select>";

$sortby_button = @$_REQUEST['order_by_popularity']
	? '<input type="submit" value="Sort alphabetically" onclick="document.getElementById(\'sortby_input\').value = \'\';" style="float: right;">'
	: '<input type="submit" value="Sort by usage" onclick="document.getElementById(\'sortby_input\').value = \'order_by_popularity\';" style="float: right;">';
$sortby_input = @$_REQUEST['order_by_popularity']
	? '<input type="hidden" id="sortby_input" name="order_by_popularity" value="1">'
	: '<input type="hidden" id="sortby_input" name="order_by_popularity" value="">';


$template = str_replace('{ForeachKeyword}', $foreach_kwd, $template);
$template = str_replace('{ForeachKeywordJs}', $foreach_kwd_js, $template);
$template = str_replace('{KeywordMessage}', $keyword_message, $template);
$template = str_replace('{UserHyperlinksImport}', $user_hyperlinks_import, $template);
$template = str_replace('{sortby_button}', $sortby_button, $template);
$template = str_replace('{sortby_input}', $sortby_input, $template);
$template = str_replace('{kwd_select}', $kwd_select, $template);

echo($template);

?>
