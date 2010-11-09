<?php

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

if (!is_logged_in()) {
        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
        return;
}

error_log(print_r($LOOKUPS, 1));

mysql_connection_db_overwrite(DATABASE);
$template = file_get_contents('follow_links.html');
$template = str_replace('[logged-in-user-id]', intval(get_user_id()), $template);

$wg_ids = mysql__select_array(USERS_DATABASE.'.UserGroups', 'ug_group_id', 'ug_user_id='.get_user_id());
array_push($wg_ids, 0);

if (@$_REQUEST['bkmk_id']) {
	$res = mysql_query('select * from usrBookmarks where bkm_ID = '.intval($_REQUEST['bkmk_id']));
	$bkmk = mysql_fetch_assoc($res);
	$res = mysql_query('select records.* from usrBookmarks left join records on bkm_recID=rec_id where bkm_ID = '.$bkmk['bkm_ID'].' and (rec_wg_id in ('.join(',', $wg_ids).') or rec_visibility="viewable")');
	$bib = mysql_fetch_assoc($res);
	$_REQUEST['bib_id'] = $bib['rec_id'];
}
else if (@$_REQUEST['bib_id']) {
	$res = mysql_query('select * from usrBookmarks where bkm_recID = '.intval($_REQUEST['bib_id']).' and bkm_UGrpID = '.get_user_id());
	$bkmk = mysql_fetch_assoc($res);
	$res = mysql_query('select * from records where rec_id = '.intval($_REQUEST['bib_id']).' and (rec_wg_id in ('.join(',', $wg_ids).') or rec_visibility="viewable")');
	$bib = mysql_fetch_assoc($res);
	$_REQUEST['bkmk_id'] = $bkmk['bkm_ID'];
}

$_REQUEST['rec_id'] = $_REQUEST['bib_id'];
$_REQUEST['bkm_ID'] = $_REQUEST['bkmk_id'];

$lexer = new Lexer($template);
$body = new BodyScope($lexer);

$body->global_vars['rec_id'] = $_REQUEST['rec_id'];
$body->global_vars['bkm_ID'] = $_REQUEST['bkm_ID'];

$my_kwds = mysql__select_array('keyword_links left join usrTags on kwl_kwd_id=tag_ID', 'tag_Text', 'kwl_pers_id='.$bkmk['bkm_ID']);

$keywords = mysql__select_assoc('usrBookmarks
								 left join keyword_links on bkm_ID=kwl_pers_id
								 left join usrTags on kwl_kwd_id=tag_ID
								 left join '.USERS_DATABASE.'.Users on Id=tag_UGrpID',
								'tag_Text', 'count(tag_ID) as kcount',
								'bkm_recID='.$bib['rec_id'].'
								 and kwl_id is not null
								 and Active="Y"
								 group by tag_Text
								 order by kcount desc, tag_Text');

/*
$res = mysql_query('select concat(firstname," ",lastname) as bkmk_user, tag_Text
					from usrBookmarks
					left join keyword_links on bkm_ID=kwl_pers_id
					left join usrTags on kwl_kwd_id=tag_ID
					left join '.USERS_DATABASE.'.Users on bkm_UGrpID=Id
					where bkm_recID='.$bib['rec_id'].' and kwl_id is not null order by bkmk_user, tag_Text');

$user_keywords = array();
while ($row = mysql_fetch_assoc($res)) {
	$bkmk_user = $row['bkmk_user'];
	$kwd_name = $row['tag_Text'];

	if ($user_keywords[$bkmk_user])
		array_push($user_keywords[$bkmk_user], $kwd_name);
	else
		$user_keywords[$bkmk_user] = array($kwd_name);
}
*/

if ($keywords) {
	$kwd_list = '';
	foreach ($keywords as $keyword => $count) {
		$kwd_list .= ' <tr>';
		$kwd_list .= '  <td style="vertical-align: top;"><nobr><a target=_top href="../?w=all&q=tag:%22'.urlencode($keyword).'%22" onclick="opener.location.href = this.href; window.close();" title="Search for references with the keyword \''.$keyword.'\'">'
											. (in_array($keyword, $my_kwds) ? '<b>'.htmlspecialchars($keyword).'</b>' : htmlspecialchars($keyword))
											. "</a>&nbsp;</nobr></td>\n";

		$kwd_list .= "  <td>\n";
		$res = mysql_query('select Id, concat(firstname," ",lastname) as bkmk_user
							from usrBookmarks
							left join keyword_links on bkm_ID=kwl_pers_id
							left join usrTags on kwl_kwd_id=tag_ID
							left join '.USERS_DATABASE.'.Users on bkm_UGrpID=Id
							where bkm_recID='.$bib['rec_id'].'
							and kwl_id is not null
							and tag_Text="'.$keyword.'"
							and Active="Y"
							order by bkmk_user');
		$i = 0;
		while ($row = mysql_fetch_assoc($res)) {
			if ($i++ == 3) {
				$kwd_list .= '   <span class="collapsed"><span class="hide_on_collapse">'."\n";
			}
			$kwd_list .= '   <a href="'.HEURIST_SITE_PATH.'admin/users/user.php?Id='.$row['Id'].'" title="View user profile for '.$row['bkmk_user'].'"><nobr>'.$row['bkmk_user']."</nobr></a>&nbsp;\n";
		}
		if ($i > 3) {
			$kwd_list .= '   </span>'."\n";
			$kwd_list .= '   <span class="show_inline_on_collapse" style="cursor: pointer;" onclick="parentNode.className=\'\';">...  (show all '.$count.')</span>'."\n";
			$kwd_list .= '   </span>'."\n";
		}
		$kwd_list .= '  </td>';
		$kwd_list .= ' </tr>';
	}
} else {
	$kwd_list = '<tr><td>(no matching tags)</td></tr>';
}

$body->global_vars['keyword-list'] = $kwd_list;


$res = mysql_query('
   select Id, concat(firstname," ",lastname) as bkmk_user
     from records
left join usrBookmarks on bkm_recID=rec_id
left join keyword_links on kwl_pers_id=bkm_ID
left join usrTags on tag_ID=kwl_kwd_id and tag_UGrpID=bkm_UGrpID
left join '.USERS_DATABASE.'.Users on Id=bkm_UGrpID
    where rec_id='.$bib['rec_id'].'
      and tag_ID is null
      and Id is not null
      and Id!='.get_user_id().'
	  and Active="Y"
 order by bkmk_user;');

if (mysql_num_rows($res)) {
	$body->global_vars['other-users'] .= "<tr><td>No tags</td><td>\n";
	$i = 0;
	while ($row = mysql_fetch_assoc($res)) {
		if ($i++ == 3) {
			$body->global_vars['other-users'] .= ' <span class="collapsed"><span class="hide_on_collapse">'."\n";
		}
		$body->global_vars['other-users'] .= ' <a href="'.HEURIST_SITE_PATH.'admin/users/user.php?Id='.$row['Id'].'" title="View user profile for '.$row['bkmk_user'].'"><nobr>'.$row['bkmk_user']."</nobr></a>&nbsp;\n";
	}
	if ($i > 3) {
		$body->global_vars['other-users'] .= ' </span>'."\n";
		$body->global_vars['other-users'] .= ' <span class="show_inline_on_collapse" style="cursor: pointer;" onclick="parentNode.className=\'\';">... (show all)</span>'."\n";
		$body->global_vars['other-users'] .= ' </span>'."\n";
	}
	$body->global_vars['other-users'] .= "</td></tr>\n";
}
else {
	$body->global_vars['other-users'] = '';
}



/*
$body->global_vars['user-keywords'] = '';
if ($user_keywords) {
	foreach ($user_keywords as $usr => $kwds) {
		$body->global_vars['user-keywords'] .= "  <tr>\n    <td>" . $usr . ": &nbsp;&nbsp;</td>\n    <td>\n";
		foreach ($kwds as $kwd) {
			$body->global_vars['user-keywords'] .= '      <a href="search.php?search_type=records&_simple_BIBLIO_search_search=1&biblio_search_all=true&search_advanced_search=1&adv_search=show&sort_order_dropdown=title&search_tagString=%22'.urlencode($kwd).'%22" onclick="opener.location.href = this.href; window.close();">'.htmlspecialchars($kwd)."</a>\n";
		}
		$body->global_vars['user-keywords'] .= "    </td>\n  </tr>\n";
	}
}
*/

$body->verify();
$body->render();

?>
