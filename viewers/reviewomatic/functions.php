<?php

require_once('db.php');
mysql_connection_select('heuristdb');

function get_class_grp_id($classes) {
	if (@$_REQUEST['c']  &&  array_key_exists($_REQUEST['c'], $classes)) {
		return $_REQUEST['c'];
	} else if (count($classes) == 1) {
		$class_ids = array_keys($classes);
		return $class_ids[0];
	}
	return '';
}

function get_ass_kwd_id($assignments, $class_grp_id) {
	if (@$_REQUEST['a']  &&  array_key_exists($_REQUEST['a'], $assignments)) {
		return $_REQUEST['a'];
	} else if (count($assignments) == 1) {
		$ass_ids = array_keys($assignments);
		return $ass_ids[0];
	}
	return '';
}

function get_classes($user_id) {
	$user_id = intval($user_id);
	$res = mysql_query('SELECT grp_id, grp_name
						  FROM ACLAdmin.UserGroups
					 LEFT JOIN ACLAdmin.Groups ON grp_id=ug_group_id
						 WHERE ug_user_id = ' . $user_id . '
						   AND grp_type = "UgradClass"
					  ORDER BY grp_name');
	while ($row = mysql_fetch_assoc($res)) {
		$classes[$row['grp_id']] = $row['grp_name'];
	}
	return $classes;
}

function get_assignments($grp_id) {
	$grp_id = intval($grp_id);
	$res = mysql_query('SELECT kwd_id, kwd_name
						  FROM keywords
						 WHERE kwd_wg_id=' . $grp_id . '
						   AND kwd_name LIKE "Assignment: %"
					  ORDER BY kwd_name');
	while ($row = mysql_fetch_assoc($res)) {
		$assignments[$row['kwd_id']] = $row['kwd_name'];
	}
	return $assignments;
}

function get_all_reviews($class_grp_id, $ass_kwd_id, $index=0, $count=0) {
	return get_reviews(0, $class_grp_id, $ass_kwd_id, false, $index, $count);
}
function get_reviews($user_id, $class_grp_id, $ass_kwd_id, $get_text=false, $index=0, $count=0) {
	$user_id = intval($user_id);
	$class_grp_id = intval($class_grp_id);
	$ass_kwd_id = intval($ass_kwd_id);
	$reviews = array();
	$res = mysql_query('SELECT SQL_CALC_FOUND_ROWS
							   rec_id, rec_title, rec_url, bkm_ID, bkm_Added,
							   concat(firstname," ",lastname) as author'.($get_text?', pers_notes':'').'
						  FROM keyword_links
					 LEFT JOIN usrBookmarks ON bkm_ID=kwl_pers_id
					 LEFT JOIN records ON rec_id=kwl_rec_id
					 LEFT JOIN ACLAdmin.Users on Id=pers_usr_id
						 WHERE kwl_kwd_id=' . $ass_kwd_id .
		 ($user_id > 0 ? ' AND pers_usr_id=' . $user_id : '').'
						 ORDER BY bkm_Added desc
	   '.($count > 0 ? ' LIMIT ' . $index . ', ' . $count : ''));

	$res2 = mysql_query('SELECT FOUND_ROWS()');
    $row = mysql_fetch_row($res2);
	$total = $row[0];

	while ($row = mysql_fetch_assoc($res)) {
		$review['bib_id'] = $row['rec_id'];
		$review['bkmk_id'] = $row['bkm_ID'];
		$review['title'] = $row['pers_title'] ? $row['pers_title'] : $row['rec_title'];
		$review['url'] = $row['rec_url'];
		$review['added'] = $row['bkm_Added'];
		$review['author'] = $row['author'];
		if ($get_text) {
			$review['text'] = $row['pers_notes'];
		}
		@list($review['genre_id'], $review['genre_label']) = get_genre($row['bkm_ID'], $class_grp_id);
		$reviews[] = $review;
	}

	if ($count > 0)
		return array($reviews, $total);
	else
		return $reviews;
}

function get_genre($bkmk_id, $class_grp_id) {
	$bkmk_id = intval($bkmk_id);
	$class_grp_id = intval($class_grp_id);
	$res = mysql_query('SELECT kwd_id, kwd_name
						  FROM keyword_links
					 LEFT JOIN keywords ON kwd_id=kwl_kwd_id
						 WHERE kwl_pers_id=' . $bkmk_id . '
						   AND kwd_wg_id=' . $class_grp_id . '
						   AND kwd_name like "Genre: %"');
	if ($row = mysql_fetch_assoc($res)) {
		return array($row['kwd_id'], str_replace('Genre: ', '', $row['kwd_name']));
	} else {
		return '';
	}
}

function have_bkmk_permissions($bkmk_id, $user_id) {
	$bkmk_id = intval($bkmk_id);
	$user_id = intval($user_id);
	$res = mysql_query('SELECT *
						  FROM usrBookmarks
					 LEFT JOIN records ON pers_rec_id=rec_id
						 WHERE bkm_ID=' . $bkmk_id . '
						   AND pers_usr_id=' . $user_id);
	return (mysql_num_rows($res) > 0);
}

function get_review($bkmk_id, $class_grp_id) {
	$bkmk_id = intval($bkmk_id);
	$res = mysql_query('SELECT bkm_ID, pers_notes, rec_id, rec_title, rec_url
						  FROM usrBookmarks
					 LEFT JOIN records ON rec_id=pers_rec_id
						 WHERE bkm_ID=' . $bkmk_id);
	if ($row = mysql_fetch_assoc($res)) {
		$review['bib_id'] = $row['rec_id'];
		$review['bkmk_id'] = $row['bkm_ID'];
		$review['title'] = $row['pers_title'] ? $row['pers_title'] : $row['rec_title'];
		$review['url'] = $row['rec_url'];
		@list($review['genre_id'], $review['genre_label']) = get_genre($row['bkm_ID'], $class_grp_id);

		$matches = '';
		preg_match_all("/^(?:{rating:([0-9]*)})?(?:{headline:([^}]*)})?(.*)/ms", $row['pers_notes'], $matches);
		$review['rating'] = $matches[1][0];
		$review['headline'] = $matches[2][0];
		$review['text'] = $matches[3][0];
	}
	$review['tags'] = array();
	$res = mysql_query('SELECT kwd_name
						  FROM keyword_links
					 LEFT JOIN keywords ON kwd_id = kwl_kwd_id
						 WHERE kwl_pers_id = '.$bkmk_id.'
						   AND kwd_usr_id = '.get_user_id().'
						   AND kwd_wg_id is null
					  ORDER BY kwl_order');
	while ($row = mysql_fetch_assoc($res)) {
		array_push($review['tags'], $row['kwd_name']);
	}
	return $review;
}

function get_genres($class_grp_id) {
	$class_grp_id = intval($class_grp_id);
	$genres = array();
	$res = mysql_query('SELECT kwd_id, kwd_name
						  FROM keywords
						 WHERE kwd_wg_id=' . $class_grp_id . '
						   AND kwd_name like "Genre: %"
					  ORDER BY kwd_name');
	while ($row = mysql_fetch_assoc($res)) {
		$genres[$row['kwd_id']] = str_replace('Genre: ', '', $row['kwd_name']);
	}
	return $genres;
}

function get_similar_review($url, $reviews) {
	$noproto_url = preg_replace('!^(?:http://)?(?:www[.])?([^/]*).*!', '\1', $url);  // URL minus the protocol + possibly www.
																				// and minus slash onwards
	foreach ($reviews as $review)
		if (strpos($review['url'], $noproto_url) !== false) return $review;
	return '';
}

function get_matching_bib_id($url) {
	$noproto_url = preg_replace('!^(?:http://)?(?:www[.])?(.*)!', '\1', $url);  // URL minus the protocol + possibly www.
	$res = mysql_query('SELECT rec_id
						  FROM records
						 WHERE rec_url like "http://'.addslashes($noproto_url).'"
							OR rec_url like "http://'.addslashes($noproto_url).'/"
						 	OR rec_url like "http://www.'.addslashes($noproto_url).'"
						 	OR rec_url like "http://www.'.addslashes($noproto_url).'/"');
	if ($row = mysql_fetch_assoc($res)) {
		return $row['rec_id'];
	} else {
		return '';
	}
}

function get_similar_bibs($url) {
	$bibs = array();
	$noproto_url = preg_replace('!^(?:http://)?(?:www[.])?([^/]*).*!', '\1', $url);  // URL minus the protocol + possibly www.
																				// and minus slash onwards
	$res = mysql_query('SELECT rec_id, rec_title, rec_url
						  FROM records
						 WHERE rec_url like "http://'.addslashes($noproto_url).'%"
						 	OR rec_url like "http://www.'.addslashes($noproto_url).'%"');
	while ($row = mysql_fetch_assoc($res)) {
		$bibs[] = $row;
	}
	return $bibs;
}

function insert_bib($url, $title, $user_id) {
	if (! (@$url  &&  @$title)) return;
	$user_id = intval($user_id);
	mysql_connection_overwrite('heuristdb');
	mysql_query('set @logged_in_user_id = ' . get_user_id());
	mysql__insert('records', array(
		'rec_url' => addslashes($url),
		'rec_title' => addslashes($title),
		'rec_added' => date('Y-m-d H:i:s'),
		'rec_modified' => date('Y-m-d H:i:s'),
		'rec_added_by_usr_id' => intval(get_user_id()),
		'rec_reftype' => 1,	// internet bookmark
		'rec_workgroup' => 0));
	$bib_id = mysql_insert_id();
	mysql__insert('rec_detail', array(
		'bd_rec_id' => $bib_id,
		'bd_type' => 160,	// title
		'bd_val' => addslashes($title)));
	return $bib_id;
}

function add_review($bib_id, $title, $ass_kwd_id, $genre_id, $user_id) {
	$bib_id = intval($bib_id);
	$ass_kwd_id = intval($ass_kwd_id);
	$genre_id = intval($genre_id);
	$user_id = intval($user_id);
	mysql_connection_overwrite('heuristdb');
	mysql__insert('usrBookmarks', array(
		'pers_title' => addslashes($title),
		'pers_rec_title' => addslashes($title),
		'pers_rec_id' => $bib_id,
		'bkm_Added' => date('Y-m-d H:i:s'),
		'pers_modified' => date('Y-m-d H:i:s'),
		'pers_usr_id' => $user_id));
	$bkmk_id = mysql_insert_id();
	mysql__insert('keyword_links', array(
		'kwl_pers_id' => $bkmk_id,
		'kwl_rec_id' => $bib_id,
		'kwl_kwd_id' => $ass_kwd_id));
	mysql__insert('keyword_links', array(
		'kwl_pers_id' => $bkmk_id,
		'kwl_rec_id' => $bib_id,
		'kwl_kwd_id' => $genre_id));
	return $bkmk_id;
}

function delete_review($bkmk_id) {
	mysql_connection_overwrite('heuristdb');
	mysql_query('DELETE FROM usrBookmarks WHERE bkm_ID=' . intval($bkmk_id));
	mysql_query('DELETE FROM keyword_links WHERE kwl_pers_id=' . intval($bkmk_id));
}

function print_error($err) {
?>
<html>
 <head>
  <link rel=stylesheet type=text/css href=chesher.css>
 </head>
 <body>
  <div id=banner>
   <div class=heading>Review-o-matic</div>
  </div>
  <br>
  <div class=heading2>error: <?=$err?></div>
  <br>
  <div><a href=.>Review-o-matic home</a></div>
 </body>
</html>
<?php
}








// assignment details: may come from a table later
function get_assignment_review_count($ass_kwd_id) {
	return 3;
}
function get_assignment_tag_count($ass_kwd_id) {
	return 5;
}
function get_assignment_details($ass_kwd_id) {
	$detail = "
<table cellpadding='30'>
<tr valign='top'>
<td width='60%'  bgcolor='C0CFCF'>
<h1>ARIN2610 Web Production</h1>

<h2>Website reviews (20%)</h2>

<h3>Due: Friday 7th September</h3>

<p>Choose one website from each of three significant web genres:  
Academic, Web 2.0 and Institutional. Reserve your review sites as  
soon as possible, because you will not be able to write a review of  
the same site as another student.</p>

<p>The three genres have quite different purposes and locations:</p>
<ul>

<li>Academic content websites are developed to publish writing (and  
other media) for an academic audience, most often with content that  
has been peer-reviewed.</li>

<li>Web 2.0 Social websites usually allow (or require) users to  
register, and then offer users powerful ways that users can write  
text, upload content, and connecting with other users. Many such  
sites use advanced web page design using technologies such as DHTML,  
Javascript, Ruby on Rails.</li>

<li>Institutional websites represent an organisation, and provide  
ways that those within and outside can interact with it. Institutions  
can be companies, museums, galleries, government agencies, non- 
government agencies, political parties,  etc.</li>
</ul>

<p style='color: red; font-weight: bold;'>Note: the deadline for submitting
the Review Assignment has now passed. You can still submit your reviews,
but your mark will be reduced by 2% for each day overdue.</p>

</td>
<td bgcolor='CFCFCF'>


<h2>Advice on writing the review</h2>

<p>Look through the site comprehensively. Who created it? Who paid for  
it? Who are the intended audiences? How are they expected to use the  
site? Analyse the writing: is it formal or informal? Is it for a  
specific or a general audience? Has it been edited well? Consider the  
design: typography; choice of images; interactivity; flow from page- 
to-page. What are the technical features? (is this a flat site, or a  
dynamic site? does it use Flash, DHTML, CSS, RSS?) What are the main  
headings and links from the front page? How is information on the  
site organised? What transactions does it offer the user?</p>

<p>Write a short review (<400 words) of each site. In such a short  
review you won't be able to address the questions above in any depth,  
so the challenge is to identify the most salient feature for each  
site for its intended audience, and focus on that. Make sure that  
your opening sentence addresses this point, and opens your argument.  
Give the reader a concise account of the context, content and  
interactivity of the sites (remember that your reader hasn't seen it  
yet). Develop an argument: do not simply say that the site is 'good'  
or 'bad', 'successful' or 'unsuccessful', but explain how the site  
should work, considering its ambitions, and then evaluate to what  
extent it succeeds.</p>

<p>Choose up to five tags that describe the site. Tags should make it  
likely that users searching for sites like the one you are reviewing  
will write a search query including at least one of your tags. [note:  
this might appear as a pop-up next to the tags]</p>


</td>
</tr>
</table>
";

	return $detail;
}

?>
