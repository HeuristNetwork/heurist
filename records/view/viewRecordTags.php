<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* T1000 tag viewer 
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Util 
*/


define('T1000_DEBUG',1);

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

if (!is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php');
	return;
}

mysql_connection_overwrite(DATABASE);
$template = file_get_contents('viewRecordTags.html');
$template = str_replace('[logged-in-user-id]', intval(get_user_id()), $template);

$wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks', 'ugl_GroupID', 'ugl_UserID='.get_user_id());
array_push($wg_ids, get_user_id());

if (@$_REQUEST['bkmk_id']) {
	$res = mysql_query('select * from usrBookmarks where bkm_ID = '.intval($_REQUEST['bkmk_id']));
	$bkmk = mysql_fetch_assoc($res);
	$res = mysql_query('select Records.* from usrBookmarks left join Records on bkm_recID=rec_ID '.
						'where bkm_ID = '.$bkmk['bkm_ID'].
						' and (rec_OwnerUGrpID in ('.join(',', $wg_ids).') or not rec_NonOwnerVisibility="hidden")');
	$bib = mysql_fetch_assoc($res);
	$_REQUEST['recID'] = $bib['rec_ID'];
}else if (@$_REQUEST['recID']) {
	$res = mysql_query('select * from usrBookmarks where bkm_recID = '.intval($_REQUEST['recID']).' and bkm_UGrpID = '.get_user_id());
	$bkmk = mysql_fetch_assoc($res);
	$res = mysql_query('select * from Records where rec_ID = '.intval($_REQUEST['recID']).' and (rec_OwnerUGrpID in ('.join(',', $wg_ids).') or not rec_NonOwnerVisibility="hidden")');
	$bib = mysql_fetch_assoc($res);
	$_REQUEST['bkmk_id'] = $bkmk['bkm_ID'];
}
$_REQUEST['bkm_ID'] = $_REQUEST['bkmk_id'];

$lexer = new Lexer($template);
$body = new BodyScope($lexer);

$body->global_vars['LINKED_BIBLIO-ID'] = $_REQUEST['recID'];
$body->global_vars['rec_ID'] = $_REQUEST['recID'];
$body->global_vars['bkm_ID'] = $_REQUEST['bkm_ID'];

$my_kwds = mysql__select_array('usrRecTagLinks left join usrTags on rtl_TagID=tag_ID', 'tag_Text', 'rtl_RecID='.$bib['rec_ID']);

$tags = mysql__select_assoc('usrRecTagLinks left join usrTags on rtl_TagID=tag_ID'.
								' left join '.USERS_DATABASE.'.sysUGrps usr on usr.ugr_ID=tag_UGrpID',
								'tag_Text', 'count(tag_ID) as kcount',
								'rtl_RecID='.$_REQUEST['recID'].
								' and rtl_ID is not null'.
								' and usr.ugr_Enabled="Y"'.
								' group by tag_Text'.
								' order by kcount desc, tag_Text');


if ($tags) {
	$kwd_list = '';
	foreach ($tags as $tag => $count) {
		$kwd_list .= ' <tr>';
		$kwd_list .= '  <td style="vertical-align: top;white-space:nowrap;"><a target=_top href="'.HEURIST_BASE_URL.'?w=all&q=tag:%22'.urlencode($tag).'%22&db='.HEURIST_DBNAME.'" onclick="opener.location.href = this.href; window.close();" title="Search for references with the tag \''.$tag.'\'">'
											. (in_array($tag, $my_kwds) ? '<b>'.htmlspecialchars($tag).'</b>' : htmlspecialchars($tag))
											. "</a>&nbsp;</td>\n";

		$kwd_list .= "  <td style=\"vertical-align: top;\">\n";
		$res = mysql_query('select usr.ugr_ID, concat(usr.ugr_FirstName," ",usr.ugr_LastName) as bkmk_user
							from usrBookmarks
							left join usrRecTagLinks on bkm_RecID=rtl_RecID
							left join usrTags on rtl_TagID=tag_ID
							left join '.USERS_DATABASE.'.sysUGrps usr on bkm_UGrpID=usr.ugr_ID
							where bkm_recID='.$bib['rec_ID'].'
							and rtl_ID is not null
							and tag_Text="'.$tag.'"
							and usr.ugr_Enabled="Y"
							order by bkmk_user');
		$i = 0;
		while ($row = mysql_fetch_assoc($res)) {
			if ($i++ == 3) {
				$kwd_list .= '   <span class="collapsed"><span class="hide_on_collapse">'."\n";
			}
			$kwd_list .= '   <a href="'.HEURIST_BASE_URL.'admin/ugrps/viewUserDetails.php?Id='.$row['ugr_ID'].
									'" style="white-space:nowrap;" title="View user profile for '.
									$row['bkmk_user'].'">'.$row['bkmk_user']."</a>&nbsp;\n";
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

$body->global_vars['tag-list'] = $kwd_list;


$res = mysql_query('
   select usr.ugr_ID, concat(usr.ugr_FirstName," ",usr.ugr_LastName) as bkmk_user
     from Records
left join usrBookmarks on bkm_recID=rec_ID
left join usrRecTagLinks on rtl_RecID=bkm_RecID
left join usrTags on tag_ID=rtl_TagID and tag_UGrpID=bkm_UGrpID
left join '.USERS_DATABASE.'.sysUGrps usr on usr.ugr_ID=bkm_UGrpID
    where rec_ID='.$bib['rec_ID'].'
      and tag_ID is null
      and usr.ugr_ID is not null
      and usr.ugr_ID!='.get_user_id().'
	  and usr.ugr_Enabled="Y"
 order by bkmk_user;');

if (mysql_num_rows($res)) {
	$body->global_vars['other-users'] .= "<tr><td>No tags</td><td>\n";
	$i = 0;
	while ($row = mysql_fetch_assoc($res)) {
		if ($i++ == 3) {
			$body->global_vars['other-users'] .= ' <span class="collapsed"><span class="hide_on_collapse">'."\n";
		}
		$body->global_vars['other-users'] .= ' <a href="'.HEURIST_BASE_URL.'admin/ugrps/viewUserDetails.php?Id='.$row['ugr_ID'].'" title="View user profile for '.$row['bkmk_user'].'" style="white-space:nowrap;">'.$row['bkmk_user']."</a>&nbsp;\n";
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
$body->global_vars['user-tags'] = '';
if ($user_tags) {
	foreach ($user_tags as $usr => $kwds) {
		$body->global_vars['user-tags'] .= "  <tr>\n    <td>" . $usr . ": &nbsp;&nbsp;</td>\n    <td>\n";
		foreach ($kwds as $kwd) {
			$body->global_vars['user-tags'] .= '      <a href="search.php?search_type=records&_simple_BIBLIO_search_search=1&biblio_search_all=true&search_advanced_search=1&adv_search=show&sort_order_dropdown=title&search_tagString=%22'.urlencode($kwd).'%22" onclick="opener.location.href = this.href; window.close();">'.htmlspecialchars($kwd)."</a>\n";
		}
		$body->global_vars['user-tags'] .= "    </td>\n  </tr>\n";
	}
}
*/

$body->verify();
$body->render();

?>
