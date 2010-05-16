<?php

require('cred.php');
require('functions.php');

if (! is_logged_in()) {
	header('Location: login.php');
	return;
}

// ----- load classes, assignments, reviews
$classes = get_classes(get_user_id());
$class_grp_id = get_class_grp_id($classes);
if (! $class_grp_id) {
	print_error("no class");
	return;
}
$assignments = get_assignments($class_grp_id);
$ass_kwd_id = get_ass_kwd_id($assignments, $class_grp_id);
if (! $ass_kwd_id) {
	print_error("no assignment");
	return;
}
$all_reviews = get_all_reviews($class_grp_id, $ass_kwd_id);
$reviews = get_reviews(get_user_id(), $class_grp_id, $ass_kwd_id);
if (count($reviews) >= get_assignment_review_count($ass_kwd_id)) {
	print_error("cannot add any more reviews for this assignment");
	return;
}
$genres = get_genres($class_grp_id);

if (@$_REQUEST['url']  &&  @$_REQUEST['title']) {
	if (substr($_REQUEST['url'], 0, 7) != 'http://')
		$_REQUEST['url'] = 'http://' . $_REQUEST['url'];
	$similar_review = get_similar_review($_REQUEST['url'], $all_reviews);
	if ($similar_review  &&  ! @$_REQUEST['confirm_review']) {
		$show_confirm_review = true;
	} else {
		$bib_id = get_matching_bib_id($_REQUEST['url']);
		if (! $bib_id) {
			$similar_bibs = get_similar_bibs($_REQUEST['url']);
			if (count($similar_bibs) > 0  &&  ! array_key_exists('bib_id', $_REQUEST)) {
				$show_select_bib = true;
			} else if (count($similar_bibs) > 0  &&  @$_REQUEST['bib_id'] > 0) {
				$bib_id = $_REQUEST['bib_id'];
			} else {
				$bib_id = insert_bib($_REQUEST['url'], $_REQUEST['title'], get_user_id());
			}
		}
		if ($bib_id) {
			$bkmk_id = add_review($bib_id, $_REQUEST['title'], $ass_kwd_id, @$_REQUEST['genre'], get_user_id());
			header('Location: edit.php?c='.$class_grp_id.'&a='.$ass_kwd_id.'&id='.$bkmk_id);
		}
	}
}

?>

<html>
 <head>
  <title>Review-o-matic - Add review</title>
  <link rel=stylesheet type=text/css href=chesher.css>
  <style type=text/css>
#submit_cell {
	text-align: right;
}
  </style>
  <script type=text/javascript>
	function check_inputs() {
		if (document.getElementById('url_input').value == ''  ||
			document.getElementById('title_input').value == '') {
			alert ('please enter a url and a title');
			return false;
		}
		return true;
	}
  </script>
 </head>
 <body>
 
 <form method=post onsubmit="return check_inputs();">
 <input type=hidden name=c value=<?= $class_grp_id ?>>
 <input type=hidden name=a value=<?= $ass_kwd_id ?>>

 <div id=banner>
  <div class=heading>Review-o-matic - Add review</div>
 </div>

<?php
	if (! @$show_confirm_review  &&  ! @$show_select_bib) {
?>

 <table id=form_table cellspacing=10 cellpadding=0 border=0>

  <tr>
   <td><nobr>Website URL</nobr></td>
   <td>http://<input name=url id=url_input></td>
  </tr>

  <tr>
   <td><nobr>Website title</nobr></td>
   <td><input name=title id=title_input></td>
  </tr>

  <tr>
   <td>Genre</td>
   <td><?php print_genre_select($genres); ?></td>
  </tr>

  <tr>
   <td></td>
   <td id=submit_cell><input type=submit value=add id=submit_button></td>
  </tr>

 </table>

<?php
	} else if (@$show_confirm_review) {
?>

 <input type=hidden name=url value=<?= $_REQUEST['url']?>> 
 <input type=hidden name=title value=<?= $_REQUEST['title']?>> 
 <input type=hidden name=genre value=<?= $_REQUEST['genre']?>> 
 <input type=hidden name=confirm_review value=1>
 <p>You entered:<br>
 <?= $_REQUEST['title'] ?> (<a href=<?= $_REQUEST['url'] ?>><?= $_REQUEST['url'] ?></a>)</p>
 <p>It looks like somebody else has already reviewed something similar:<br>
 <?= $similar_review['title'] ?> (<a href=<?= $similar_review['url'] ?>><?= $similar_review['url'] ?></a>)</p>
 <p>Are you SURE you want to review this site?</p>
 <input type=submit value=cofirm>
 <input type=button value=cancel onclick="document.location.href = 'add.php?c=<?=$class_grp_id?>&a=<?=$ass_kwd_id?>';">

<?php
	} else if (@$show_select_bib) {
?>

 <input type=hidden name=url value=<?= $_REQUEST['url']?>> 
 <input type=hidden name=title value=<?= $_REQUEST['title']?>> 
 <input type=hidden name=genre value=<?= $_REQUEST['genre']?>> 
 <input type=hidden name=confirm_review value=<?= @$_REQUEST['confirm_review'] ?>>
 <br>
 <div class=heading2>Did you mean...</div>
 <p>One or more matches were found in the database.<br>
 Please choose an existing entry if it is the site you mean.</p>
 <?php print_existing_bibs($similar_bibs, $_REQUEST['url']); ?>
 <br>
 <input type=submit value=continue>
 
<?php
	}
?>

 </form>

 </body>
</html>

<?php

function print_genre_select($genres) {
	print "<select id=genre_select name=genre>\n";
	foreach ($genres as $genre_id => $genre_name) {
		print " <option value=".$genre_id.">".$genre_name."</option>\n";
	}
	print "</select>\n";
}

function print_existing_bibs($bibs, $new_url) {
	foreach ($bibs as $bib) {
		print " <input type=radio name=bib_id value=".$bib['bib_id'].">\n";
		print " <a target=_new href=".$bib['bib_url'].">".$bib['bib_url']."</a><br>\n";
	}
	print " <br>\n";
	print " <input type=radio name=bib_id value=0 checked>\n";
	print " <a target=_new href=".$new_url.">".$new_url."</a><br>\n";
}

?>
