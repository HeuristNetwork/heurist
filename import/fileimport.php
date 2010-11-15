<?php
/* not suited to t-1000 */

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../common/connect/cred.php');
require_once(dirname(__FILE__).'/../common/connect/db.php');
require_once(dirname(__FILE__).'/../records/disambig/similar.php');
require_once(dirname(__FILE__).'/../common/t1000/.ht_stdefs');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}


mysql_connection_db_overwrite(DATABASE);

$nextmode = 'inputselect';

if (@$_REQUEST['shortcut']) {
	$_REQUEST['mode'] = 'Analyse';
	$_REQUEST['source'] = 'url';
	$_REQUEST['url'] = $_REQUEST['shortcut'];
}

if (@$_REQUEST['old_srcname'])
	$srcname = $_REQUEST['old_srcname'];


if (@$_REQUEST['mode'] == 'Analyse') {
	if (@$_REQUEST['source'] == 'file') {
		$src = file_get_contents($_FILES['file']['tmp_name']);
		$srcname = $_FILES['file']['name'];
	} else if (@$_REQUEST['source'] == 'url') {
		$_REQUEST['url'] = preg_replace('/#.*/', '', $_REQUEST['url']);

		$ch = curl_init(@$_REQUEST['url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		$src = curl_exec($ch);
		$error = curl_error($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (intval($code) >= 400)
			$error = 'URL could not be retrieved. <span style="font-weight: normal;">You might try saving the page you are importing, and then <a href="fileimport.php">import from file</a>.</span>';
		$srcname = @$_REQUEST['url'];
	}

	if (@$src) {
		$base_url = @$_REQUEST['url'];
		if (preg_match('!<base[^>]*href=["\']?([^"\'>\s]+)["\']?!is', $src, $url_match))
			$base_url = $url_match[1];
		$base_url_root = preg_replace('!([^:/])/.*!', '$1', $base_url);
		$base_url_base = preg_replace('!([^:/]/.*/)[^/]*$!', '$1', $base_url);
		if (substr($base_url_base, -1, 1) != '/') $base_url_base = $base_url_base . '/';
/*
error_log($base_url);
error_log($base_url_root);
error_log($base_url_base);
*/

		// clean up the page a little
		$src = preg_replace('/<!-.*?->/s', '', $src);
		$src = preg_replace('!<script.*?</script>!is', '', $src);
		$src = preg_replace('!<style.*?</style>!is', '', $src);

		// find the page title
		preg_match('!<title>([^><]*)</title>!is', $src, $title_matches);
		if (@$title_matches[1])
			$notes_src_str = " [source: '".$title_matches[1]."' (".$srcname.")]";
		else
			$notes_src_str = " [source: ".$srcname."]";

		preg_match_all('!(<a[^>]*?href=["\']?([^"\'>\s]+)["\']?[^>]*?'.'>(.*?)</a>.*?)(?=<a\s|$)!is', $src, $matches);

		/* get a list of the link-texts that we are going to ignore */
		$ignored = mysql__select_assoc('ignored_hyperlink_texts', 'lcase(hyp_text)', '-1',
		                               'hyp_usr_id is null or hyp_usr_id='.get_user_id());
		$wildcard_ignored = array();
		foreach ($ignored as $key => $val) {
			$key_len = strlen($key);

			if (@$key[$key_len-1] == '*') {	/* wildcard at the end of the string only */
				unset($ignored[$key]);
				$wildcard_ignored[substr($key, 0, $key_len-1)] = $key_len - 1;
			}
		}

		mysql_connection_db_select(USERS_DATABASE);
		$res = mysql_query('select WordLimit from '.USERS_TABLE.' where '.USERS_ID_FIELD.' = '.get_user_id());
		$row = mysql_fetch_row($res);
		$word_limit = $row[0];	// minimum number of words that must appear in the link
		mysql_connection_db_overwrite(DATABASE);


		$urls = array();
		$notes = array();
		$last_url = '';
		for ($i=0; $i < count($matches[1]); ++$i) {
			// ignore javascript links, mozilla 'about' links
			if (preg_match('!^(javascript|about):!i', @$matches[2][$i]))
				continue;

			if (! preg_match('!^[-+.a-z]+:!i', @$matches[2][$i])) {	/* doesn't start with protocol -- a relative URL */
				if (substr($matches[2][$i], 0, 1) == '/')	/* starts with a slash -- relative to root */
					$matches[2][$i] = $base_url_root . $matches[2][$i];
				else
					$matches[2][$i] = $base_url_base . $matches[2][$i];

				//while (preg_match('!/\\.\\.(?:/|$)!', $matches[2][$i]))	/* remove ..s */
					//$matches[2][$i] = preg_replace('!(http://.+)(?:/[^/]*)/\\.\\.(/|$)!', '\\1\\2', $matches[2][$i]);

		//		error_log($matches[1][$i]);
			}

			$matches[3][$i] = trim(preg_replace('/\s+/', ' ', str_replace('&nbsp;', ' ', strip_tags($matches[3][$i]))));

			$lcase = strtolower(html_entity_decode($matches[3][$i]));

			$forbidden = 0;
			if (@$ignored[$lcase]) $forbidden = 1;			// ignore forbidden links
			else {
				foreach ($wildcard_ignored as $wc => $len) {
					if (substr($lcase, 0, $len) == $wc) { $forbidden = 1; break; }
				}
			}
			if (! @$forbidden  and  substr($matches[3][$i], 0, 5) != 'http:') {
				if (($word_limit and ! $matches[3][$i])
				 or (substr_count($matches[3][$i], ' ')+1 < $word_limit)) $forbidden = 1;	// ignore short links
			}
			if (@$forbidden) {
				if (@$last_url) $notes[$last_url] .= strip_tags(@$matches[1][$i]);
				continue;
			}

			/* matches[2] contains the URLs, matches[3] contains the text of the link */
			if (! @$urls[$matches[2][$i]]  or  @$matches[2][$i] == @$urls[$matches[2][$i]]) {
				$url = html_entity_decode($matches[2][$i]);

				// if ($matches[2][$i] != $matches[3][$i])
					$urls[$url] = $matches[3][$i];
				/* REMOVED LIMITATION: if the text of the link is the URL itself, omit it */
				//	$urls[$matches[2][$i]] = ;	// continue;
				$notes[$url] = strip_tags($matches[1][$i]);
				$last_url = $url;
			}
		}

		foreach ($notes as $url => $val) {
			$val = preg_replace('/\s*(\n)\s*/s', "$1", $val);
			$notes[$url] = preg_replace('/[ 	]+/s', ' ', $val);
		}

		$nextmode = 'printurls';
	}

} else if (@$_REQUEST['link']) {
	$urls = array();
	$max_no = max(array_keys($_REQUEST['link']));

	for ($i=1; $i <= $max_no; ++$i) {
		if ($_REQUEST['link'][$i])
			$urls[$_REQUEST['link'][$i]] = @$_REQUEST['title'][$i];
	}

	$nextmode = 'printurls';
}


$disambiguate_bib_ids = array();
if ((@$_REQUEST['mode'] == 'Bookmark checked links'  ||  @$_REQUEST['adding_tags'])  &&  @$_REQUEST['links']) {
	$bkmk_insert_count = 0;
	foreach (@$_REQUEST['links'] as $linkno => $checked) {
		if (! @$checked) continue;

		$rec_id = biblio_check(@$_REQUEST['link'][$linkno], @$_REQUEST['title'][$linkno], @$_REQUEST['use_notes'][$linkno]? @$_REQUEST['notes'][$linkno] . @$notes_src_str : NULL, @$_REQUEST['rec_id'][$linkno]);
		if (is_array($rec_id)  and  $rec_id) {
			// no exact match, just a list of nearby matches; get the user to select one
			$disambiguate_bib_ids[$_REQUEST['link'][$linkno]] = $rec_id;
			continue;
		}
		if (! @$rec_id) continue;	/* malformed URL */

		if (@$_REQUEST['adding_tags']) {
			$kwd = @$_REQUEST['wgTags'];
		} else {
			$kwd = @$_REQUEST['kwd'][$linkno];
		}

		if (bookmark_insert(@$_REQUEST['link'][$linkno], @$_REQUEST['title'][$linkno], $kwd, $rec_id))
			++$bkmk_insert_count;
	}

	if (@$bkmk_insert_count == 1)
		$success = 'Added one bookmark';
	else if (@$bkmk_insert_count > 1)
		$success = 'Added ' . $bkmk_insert_count . ' bookmarks';
}


// filter the URLs (get rid of the ones already bookmarked)
if (@$urls) {
	$bkmk_urls = mysql__select_assoc('usrBookmarks left join records on rec_id = bkm_recID', 'rec_url', '1', 'bkm_UGrpID='.get_user_id());
	$ignore = array();
	foreach ($urls as $url => $title)
		if (@$bkmk_urls[$url]) $ignore[$url] = 1;
}

?>
<html>
<head><title>Import bookmarks</title>

<link rel="icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">

</head>

<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/heurist.css">
<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/import.css">

<script src="titlegrabber.js"></script>
<script src="fileimport.js"></script>

<body width=600 height=400>

<script src="<?=HEURIST_SITE_PATH?>common/js/heurist.js"></script>
<script src="<?=HEURIST_SITE_PATH?>common/php/heurist-obj-user.php"></script>
<script src="<?=HEURIST_SITE_PATH?>common/php/display-preferences.php"></script>

<script type="text/javascript">
<!--

function checkAll() {
	var i = 1;
	while (document.getElementsByName('link['+i+']').length) {
		var e = document.getElementById('flag'+i);
		if (e) {
			e.checked = true;
			var t = document.getElementById('t'+i).value;
			var n = document.getElementById('n'+i).value;
			if (n.length > t.length) {
				var e2 = document.getElementById('un'+i);
				if (e2) e2.checked = true;
			}
		}
		i++;
	}
}
function unCheckAll() {
	var i = 1;
	while (document.getElementsByName('link['+i+']').length) {
		var e = document.getElementById('flag'+i);
		if (e) {
			e.checked = false;
			e2 = document.getElementById('un'+i);
			if (e2) e2.checked = false;
		}
		i++;
	}
}

//-->
</script>

<style type="text/css">
<!--

.inline_url {
	width: 100%;
	overflow: hidden;
	padding: 2px 0px;
}
.inline_url a {
	font-size: 80%;
	text-decoration:none;
}

.inline_notes {
	font-size: 80%;
	margin-left: 10px;
	margin-bottom: 8px;
}

.first_link_row {
	background-color: lightgray;
}
.first_link_row td {
	padding: 3px 0px;
	background-color: lightgray;
}

.curved_left {
	-moz-border-radius: 20px 0px 0px 20px;
}
.curved_right {
	-moz-border-radius: 0px 0px 0px 0px;
}
-->
</style>

<iframe style="display: none;" name="grabber"></iframe>

<table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse" bordercolor="#111111" width="100%" height="16">
  <tr>
    <td><H3>Import bookmarks</H3></td>
  </tr>
</table>

<form action="fileimport.php" method="post" enctype="multipart/form-data" name="mainform" style="margin: 0px 3px;">

<input type="hidden" name="wgTags" id="wgTags">
<input type="hidden" name="adding_tags" value="0" id="adding_tags_elt">
<input type="hidden" name="titlegrabber_lock">

<?php
	if ($nextmode == 'inputselect') {
?>

<p class="normal" style="margin-left: 20px;">You can import bookmarks from your web browser favourites (exporting bookmarks creates an html file)
or by capturing hyperlinks from a web page (such as a list of links, or a page containing some
hyperlinks of interest).</p>

<p style="margin-left: 20px;" class="normal"><b>Firefox</b>: Bookmarks <b>&nbsp;&gt;&nbsp;</b> Manage Bookmarks <b>&nbsp;&gt;&nbsp;</b> File <b>&nbsp;&gt;&nbsp;</b> Export, writes <tt>bookmarks.html</tt> by default</p>
<p style="margin-left: 20px;" class="normal"><b>IE</b>: File <b>&nbsp;&gt;&nbsp;</b> Import and Export <b>&nbsp;&gt;&nbsp;</b> Export Favourites, writes <tt>bookmark.htm</tt> by default</p>

<table border="0" cellpadding="5" cellspacing="0" class="normal">
<?php		if (@$error) {	?>
 <tr><td colspan="3" style="font-color: red;"><?= $error ?></td></tr>
<?php		} ?>
 <tr>
  <td><input type="radio" name="source" value="file" id="radio_file"></td>
  <td>Import from file:</td>
  <td><input type="file" name="file" size="50" onChange="document.getElementById('radio_file').checked = true;"></td>
 </tr>
 <tr>
  <td><input type="radio" name="source" value="url" id="radio_url"></td>
  <td>Import from URL:</td>
  <td><input type="text" name="url" size="50" onChange="document.getElementById('radio_url').checked = true;"></td>
 </tr>
</table>

<br>

<input type="submit" name="mode" value="Analyse">
<?php
	} else if ($nextmode == 'printurls') {

/* removed by saw 2010/11/12 doesn't seemed to be used anymore
		$tags = mysql__select_array('usrTags', 'tag_Text', 'tag_UGrpID='.get_user_id().' order by tag_Text');
		$tag_options = '';
		foreach ($tags as $kwd)
			$tag_options .= '<option value="'.htmlspecialchars($kwd).'">'.htmlspecialchars($kwd)."</option>\n";
*/
		mysql_connection_db_select(USERS_DATABASE);
		$res = mysql_query('select WordLimit from '.USERS_TABLE.' where '.USERS_ID_FIELD.' = '.get_user_id());
		$row = mysql_fetch_row($res);
		$word_limit = $row[0];	// minimum number of words that must appear in the link
		mysql_connection_db_overwrite(DATABASE);

?>
<p>Web links in <b><?= htmlspecialchars($srcname) ?></b></p>
<input type="hidden" name="old_srcname" value="<?= htmlspecialchars($srcname) ?>">

<p style="margin-left: 20px;" class="normal">
Note: the list only shows links which you have not already bookmarked.<br>
<?php if ($word_limit) { ?>
  Only links with at least <?= ($word_limit == 1)? 'one word' : $word_limit.' words' ?> are shown,
  and common
<?php } else { ?>Common<?php } ?>
  hyperlink texts are ignored.
  &nbsp;&nbsp;
  <input type="button" onclick="top.HEURIST.util.popupURL(top, '<?=HEURIST_SITE_PATH?>user/profile/configuration.php?body_only&bookmark_import=1&section=bookmarkimport', { callback: function() { document.forms[0].submit(); } });" value="Change settings">
</p>

<p style="margin-left: 20px;" class="normal">
We recommend bookmarking a few links at a time.<br>
The list is reloaded after each addition and after change of settings.
</p>

<table border="0" cellpadding="2" cellspacing="0" class="small" style="width: 100%;">
<?php		if (@$error) {	?>
 <tr><td colspan="6" style="padding: 10px; padding-left: 20px; font-weight: bold; color: red;"><?= $error ?></td></tr>
 <tr><td colspan="6"></td></tr>
<?php		} ?>
<?php		if (@$success) {	?>
 <tr><td colspan="6" style="padding: 10px; padding-left: 20px; font-size: 150%; font-weight: bold; color: green;"><?= htmlspecialchars($success) ?></td></tr>
 <tr><td colspan="6"></td></tr>
<?php		} ?>
<?php		if (@$disambiguate_bib_ids) { ?>
 <tr><td colspan="6" style="padding: 10px; padding-left: 20px; color: red;">
  <b><?= (count($disambiguate_bib_ids) == 1)? 'One of your selected links is' : 'Some of your selected links are' ?>
  similar to record(s) already in the database.</b><br>
  The similar records are shown below: please select the appropriate page, or add a new URL to the database.<br>
  Then click on "Bookmark checked links" again.
 </td></tr>
 <tr><td colspan="6"></td></tr>
<?php		} ?>
 <tr>
  <td></td>
  <td colspan="5">
   <a href="#" onClick="checkAll(); return false;">Check all</a>
   &nbsp;&nbsp;
   <a href="#" onClick="unCheckAll(); return false;">Uncheck all</a>
   &nbsp;&nbsp;
   <input type="submit" name="mode" value="Bookmark checked links" style="font-weight: bold;" onclick="top.HEURIST.util.popupURL(window, '<?=HEURIST_SITE_PATH?>records/tags/add-tags.html', { callback: function(tags) { document.getElementById('wgTags').value = tags; document.getElementById('adding_tags_elt').value = 1; document.forms[0].submit(); } } ); return false;">
  </td>
 </tr>


 <tr><td colspan="6">&nbsp;</td></tr>

<?php
/* do two passes: first print any that need disambiguation, then do the rest */
		if (@$disambiguate_bib_ids) {
			$linkno = 0;
			foreach (@$urls as $url => $title) {
				++$linkno;
				if (! @$disambiguate_bib_ids[$url]) continue;

				print_link($url, $title);
				$ignore[$url] = 1;
			}

			print '<tr><td colspan="7">&nbsp;</td></tr>' . "\n";
		}

		$linkno = 0;
		foreach (@$urls as $url => $title) {
			++$linkno;
			if (@$ignore[$url]) {
				print '<input type="hidden" name="link['.$linkno.']" value="'.htmlspecialchars($url).'">';
				continue;	// already bookmarked; have to give the bogus element to keep PHP numbering in sync
			}

			print_link($url, $title);
		}
?>
 <tr><td colspan="6">&nbsp;</td></tr>

</table>
<?php
	}
?>
</form>

</body>
</html>
<?php
/* ----- END OF OUTPUT ----- */

function biblio_check($url, $title, $notes, $user_bib_id) {
	/*
	 * Look for a records record corresponding to the given record;
	 * user_bib_id is the user's preference if there isn't an exact match.
	 * Insert one if it doesn't already exist;
	 * return the rec_id, or 0 on failure.
	 * If there are a number of similar URLs, return a list of their bib_ids.
	 */

	$res = mysql_query('select rec_id from records where rec_url = "'.addslashes($url).'" and (rec_wg_id=0 or rec_visibility="viewable")');
	if (mysql_num_rows($res) > 0) {
		$bib = mysql_fetch_assoc($res);
		return $bib['rec_id'];
	}

	if ($user_bib_id > 0) {
		$res = mysql_query('select rec_id from records where rec_id = "'.addslashes($user_bib_id).'" and (rec_wg_id=0 or rec_visibility="viewable")');
		if (mysql_num_rows($res) > 0) {
			$bib = mysql_fetch_assoc($res);
			return $bib['rec_id'];
		}

	} else if (! $user_bib_id) {

		$bib_ids = similar_urls($url);
		if ($bib_ids) return $bib_ids;
/*
		$par_url = preg_replace('/[?].*'.'/', '', $url);
		if (substr($par_url, strlen($par_url)-1) == '/')	// ends in a slash; remove it
			$par_url = substr($par_url, 0, strlen($par_url)-1);

		$res = mysql_query('select rec_id from records where rec_url like "'.addslashes($par_url).'%" and (rec_wg_id=0 or rec_visibility="viewable")');
		if (mysql_num_rows($res) > 0) {
			$bib_ids = array();
			while ($row = mysql_fetch_row($res))
				array_push($bib_ids, $row[0]);
			return $bib_ids;
		}
*/
	}

	// no similar URLs, no exactly matching URL, or user has explicitly selected "add new URL"
	//insert the main record
	if (mysql__insert('records', array(
		'rec_type' => 1,
		'rec_url' => $url,
		'rec_added' => date('Y-m-d H:i:s'),
		'rec_modified' => date('Y-m-d H:i:s'),
		'rec_title' => $title,
		'rec_scratchpad' => $notes,
		'rec_added_by_usr_id' => get_user_id()
	))) {
		$rec_id = mysql_insert_id();
		//add title detail
		mysql__insert('rec_details', array(
			'rd_rec_id' => $rec_id,
			'rd_type' => 160,
			'rd_val' => $title
		));
		//add notes detail
		mysql__insert('rec_details', array(
			'rd_rec_id' => $rec_id,
			'rd_type' => 191,
			'rd_val' => $notes
		));
		return $rec_id;
	}

	return 0;
}

function bookmark_insert($url, $title, $tags, $rec_id) {
	/*
	 * Insert a new bookmark with the relevant details;
	 * return true on success,
	 * return false on failure, or if the records record is already bookmarked by this user.
	 */

	$res = mysql_query('select * from usrBookmarks where bkm_recID="'.addslashes($rec_id).'"
	                                              and bkm_UGrpID="'.get_user_id().'"');
	//if already bookmarked then return
	if (mysql_num_rows($res) > 0) return 0;
	//insert the bookmark
	if (mysql__insert('usrBookmarks', array(
		'bkm_recID' => $rec_id,
		'bkm_Added' => date('Y-m-d H:i:s'),
		'bkm_Modified' => date('Y-m-d H:i:s'),
		'bkm_UGrpID' => get_user_id()))) {
		$bkm_ID = mysql_insert_id();
		// find the tag ids for each tag.
		$all_tags = mysql__select_assoc('usrTags', 'lower(tag_Text)', 'tag_ID', 'tag_UGrpID='.get_user_id());
		$input_tags = explode(',', $tags);
		$tag_ids = array();

//		$kwd_string = '';
		foreach ($input_tags as $tag) {
			if ($all_tags[strtolower(trim($tag))]) {
				array_push($tag_ids, $all_tags[strtolower(trim($tag))]);
//				if ($kwd_string) $kwd_string .= ',';
//				$kwd_string .= trim($kwd);
			}
		}

//		mysql_query('delete from usrRecTagLinks where kwl_pers_id='.$bkm_ID);
		if ($tag_ids) {
			$insert_stmt = '';
			$tgi_count = 0;
			foreach ($tag_ids as $tag_id) {
				if ($insert_stmt) $insert_stmt .= ',';
				$insert_stmt .= '(' . $rec_id . ',' . $tag_id . ',' . ++$tgi_count . ')';
			}

			$insert_stmt = 'insert into usrRecTagLinks (rtl_RecID, rtl_TagID, rtl_Order) values ' . $insert_stmt;
			mysql_query($insert_stmt);
		}

		return 1;
	} else return 0;
}

function my_htmlspecialchars_decode($str) {
	return str_replace(array('&nbsp;', '&amp;', '&quot;', '&lt;', '&gt;', '&copy;'), array(' ', '&', '"', '<', '>', '(c)'), $str);
}

function print_link($url, $title) {
	global $linkno;
	global $disambiguate_bib_ids;
	global $notes;

?>
 <tr class="first_link_row">
  <td style="text-align: right; width: 30px;" class="curved_left">
   <label>&nbsp;<input type="checkbox" name="links[<?= $linkno ?>]" value="1" class="check_link" id="flag<?= $linkno ?>" <?= @$_REQUEST['links'][$linkno]? 'checked' : '' ?> onchange="var t=document.getElementById('t<?= $linkno ?>').value; var n=document.getElementById('n<?= $linkno ?>').value; if (!this.checked || n.length > t.length) { var e=document.getElementById('un<?= $linkno ?>'); if(e) e.checked = this.checked; }">&nbsp;</label>
  </td>
  <td style="width: 50%;"><input type="text" name="title[<?= $linkno ?>]" value="<?= $title ?>" style="width: 100%; font-weight: bold; background-color: #eee;" id="t<?= $linkno ?>">
      <input type="hidden" name="alt_title[<?= $linkno ?>]" value="<?= $title ?>" id="at<?= $linkno ?>">
      <input type="hidden" name="link[<?= $linkno ?>]" value="<?= htmlspecialchars($url) ?>" id="u<?= $linkno ?>">
  </td>
  <td style="text-align: center; width: 40px;">&#91;<a href="<?= $url ?>" target="_blank">visit</a>&#93;</td>
  <td style="text-align: center; width: 50px;"><input type="button" name="lookup[<?= $linkno ?>]" value="Lookup" onClick="if (value == 'Lookup') { lookupTitle(this); } else { var e1 = document.getElementById('t<?= $linkno ?>'); var e2 = document.getElementById('at<?= $linkno ?>'); var tmp = e1.value; e1.value = e2.value; e2.value = tmp; }" id="lu<?= $linkno ?>"></td>
  <td style="text-align: center; width: 50px;"><input type="hidden" name="kwd[<?= $linkno ?>]" value="<?= htmlspecialchars(@$_REQUEST['kwd'][$linkno]) ?>" id="key<?= $linkno ?>"></td>
  <td>&nbsp;</td>
 </tr>
 <tr>
  <td></td>
  <td colspan="4"><div class="inline_url"><a target=_blank href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($url) ?></a></div></td>
  <td>&nbsp;</td>
 </tr>
 <tr>
  <td></td>
  <td colspan="4">
   <table border="0" cellpadding="0" cellspacing="0">
    <tr>
     <td style="width: 100px; vertical-align: top;"><nobr>
      <label>&nbsp;<input style="margin: 0px;" type="checkbox" name="use_notes[<?= $linkno ?>]" value="1" id="un<?= $linkno ?>" class="use_notes_checkbox">&nbsp;
<?php
	if (@$_REQUEST['notes'][$linkno])
		$word_count = str_word_count($_REQUEST['notes'][$linkno]);
	else
		$word_count = str_word_count($notes[$url]);
	if ($word_count == 1) {
		print '<small>1 word</small>';
	} else if ($word_count > 1) {
		print "<small>$word_count words</small>";
	}
?></label>
      <input type="hidden" name="notes[<?= $linkno ?>]" id="n<?= $linkno ?>" value="<?= @$_REQUEST['notes'][$linkno]? str_replace('"', '\\"', htmlspecialchars($_REQUEST['notes'][$linkno])) : str_replace('"', '\\"', htmlspecialchars($notes[$url])) ?>">
  </nobr></td>
     <td style="vertical-align: top; padding-right: 20px;"><div class="inline_notes"><?= @$_REQUEST['notes'][$linkno]? htmlspecialchars($_REQUEST['notes'][$linkno]) : wordwrap($notes[$url], 50, "\n", true) ?></div></td>
    </tr>
   </table>
  </td>
  <td>&nbsp;</td>
 </tr>
<?php
	if (@$disambiguate_bib_ids[$url]) {
?>
 <tr>
  <td></td>

  <td colspan="4">
   <div style="padding-bottom:8px;">
     <label>
      <input type="radio" name="rec_id[<?= $linkno ?>]" value="-1" onclick="selectExistingLink(<?= $linkno ?>);">
      <b>New (add this URL to the database)</b>
     </label>
   </div>

<?php
		$res = mysql_query('select * from records where rec_id in (' . join(',', $disambiguate_bib_ids[$url]) . ')');
		$all_bibs = array();
		while ($row = mysql_fetch_assoc($res))
			$all_bibs[$row['rec_id']] = $row;

		foreach ($disambiguate_bib_ids[$url] as $rec_id) {
			$row = $all_bibs[$rec_id];
?>
   <div>
    <label>
     <input type="radio" name="rec_id[<?= $linkno ?>]" value="<?= $row['rec_id'] ?>" onclick="selectExistingLink(<?= $linkno ?>);">
     <?= htmlspecialchars($row['rec_title']) ?>
    </label><br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a style ="font-size: 80%; text-decoration:none;" target="_testwindow" href="<?= htmlspecialchars($row['rec_url']) ?>"><?php
			if (strlen($row['rec_url']) < 100)
				print (common_substring($row['rec_url'], $url));
			else
				print (common_substring(substr($row['rec_url'], 0, 90) . '...', $url));
    ?></a>
   </div>

<?php
		}
?>
  </td>
  <td>&nbsp;</td>
 </tr>
<?php
	}
?>
 <tr><td>&nbsp;</td></tr>
<?php
}


function common_substring($url, $base_url) {
	for ($i=0; $i < strlen($url) && $i < strlen($base_url); ++$i) {
		if ($url[$i] != $base_url[$i]) break;
	}

	return '<span style="color: black;">'.htmlspecialchars(substr($url, 0, $i)).'</span>'.htmlspecialchars(substr($url, $i));
}

?>
