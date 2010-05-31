<?php

require('cred.php');
require('functions.php');
require_once('db.php');

if (! is_logged_in()) {
	header('Location: login.php');
	return;
}

if (! @$_REQUEST['id']) {
	print_error("no id specified");
	return;
}

if (! have_bkmk_permissions($_REQUEST['id'], get_user_id())) {
	print_error("access denied");
	return;
}

// ----- load classes
$classes = get_classes(get_user_id());
$class_grp_id = get_class_grp_id($classes);
if (! $class_grp_id) {
	print_error("no class");
	return;
}
$assignments = get_assignments($class_grp_id);
$ass_kwd_id = get_ass_kwd_id($assignments, $class_grp_id);

$review = get_review($_REQUEST['id'], $class_grp_id);
if (! @$review) {
	print_eror("no such review");
	return;
}

mysql_connection_overwrite('heuristdb');

$rating = intval(@$_REQUEST['rating']);
$description = "{rating:" . ($rating > 0 ? $rating : "") . "}" . 
			   "{headline:" . addslashes(str_replace("}", "", @$_REQUEST['headline'])) . "}" .
			   addslashes(@$_REQUEST['text']);

mysql_query('UPDATE bookmarks
				SET bkmk_title = "' . addslashes(@$_REQUEST['title']) . '",
					bkmk_description = "' . $description . '",
					bkmk_modified = "' . date('Y-m-d H:i:s') . '"
			  WHERE bkmk_id = ' . intval($review['bkmk_id']));

if (@$_REQUEST['genre']  &&  $_REQUEST['genre'] != $review['genre_id']) {
	if ($review['genre_id']) {
		mysql_query('UPDATE kwd_link
						SET kwi_kwd_id = ' . intval($_REQUEST['genre']) . '
					  WHERE kwi_bkmk_id = ' . intval($review['bkmk_id']) . '
						AND kwi_kwd_id = ' . intval($review['genre_id']));
	} else {
		mysql_query('INSERT INTO kwd_link
							 SET kwi_bkmk_id = ' . intval($review['bkmk_id']) . ',
								 kwi_bib_id = ' . intval($review['bib_id']) . ',
								 kwi_kwd_id = ' . intval($_REQUEST['genre']));
	}
}

for ($i = 0; $i < get_assignment_tag_count($ass_kwd_id); $i++) {
	if (@$_REQUEST['tag'.$i] != @$review['tags'][$i]) {
		if ($review['tags'][$i]) {
			mysql_query('DELETE kwd_link
						   FROM keywords, kwd_link
						  WHERE kwd_label = "' . $review['tags'][$i] . '"
							AND kwd_user_id = ' . get_user_id() . '
							AND kwi_kwd_id = kwd_id
							AND kwi_bkmk_id = ' . intval($review['bkmk_id']));
		}
		if (@$_REQUEST['tag'.$i]) {
			$kwd_id = 0;
			$res = mysql_query('SELECT kwd_id
								  FROM keywords
								 WHERE kwd_label = "' . $_REQUEST['tag'.$i] . '"
								   AND kwd_user_id = ' . get_user_id());
			if (mysql_num_rows($res)) {
				$row = mysql_fetch_assoc($res);
				$kwd_id = intval($row['kwd_id']);
			} else {
				$res = mysql_query('INSERT INTO keywords
											SET kwd_label = "' . $_REQUEST['tag'.$i] . '",
												kwd_user_id = ' . get_user_id());
				$kwd_id = mysql_insert_id();
			}
			if ($kwd_id) {
				mysql_query('INSERT INTO kwd_link
									 SET kwi_bkmk_id = ' . intval($review['bkmk_id']) . ',
										 kwi_bib_id = ' . intval($review['bib_id']) . ',
										 kwi_kwd_id = ' . $kwd_id . ',
										 kwi_order = ' . $i);
			}
		}
	}
}

header('Location: ./?c='.$class_grp_id.'&a='.$ass_kwd_id);
?>
