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
$review = get_review($_REQUEST['id'], $class_grp_id);
if (! $review) {
	print_error("access denied");
	return;
}

if (@$_REQUEST['confirm']) {
	delete_review($review['bkmk_id']);
	header('Location: ./?c='.$class_grp_id.'&a='.$ass_kwd_id);
}


?>

<html>
 <head>
  <title>Review-o-matic - Delete review</title>
  <link rel=stylesheet type=text/css href=chesher.css>
  <style type=text/css>
#submit_cell {
	text-align: right;
}
  </style>
 </head>
 <body>
 
 <form method=post>
 <input type=hidden name=c value=<?= $class_grp_id ?>>
 <input type=hidden name=a value=<?= $ass_kwd_id ?>>
 <input type=hidden name=id value=<?= $review['bkmk_id'] ?>>
 <input type=hidden name=confirm value=1>

 <div id=banner>
  <div class=heading>Review-o-matic - Delete review</div>
 </div>

 <p>Are you sure you want to delete your review of:<p>
 <p><?= $review['title'] ?> (<a href=<?= $review['url'] ?>><?= $review['url'] ?></a>)?</p>
 <input type=submit value=delete>

 </form>

 </body>
</html>

