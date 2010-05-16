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

if (! @$_REQUEST['id']) {
	print_error("no id specified");
	return;
}

$review = get_review($_REQUEST['id'], $class_grp_id);

?>

<html>
 <head>
  <title>Review-o-matic - View review</title>
  <link rel=stylesheet type=text/css href=chesher.css>
 </head>
 <body>

 <div class=heading2><?= $classes[$class_grp_id] ?> - <?= $assignments[$ass_kwd_id] ?></div>
 <br>
 <br>

 <hr>
 <p class=heading2><?= $review['headline'] ?></p>
 <p><span class=heading2><?= $review['title'] ?></span> - <?= $review['url'] ?></p>
 <p>Rating: <?= $review['rating'] ?>%</p>
 <p>Genre: <?= $review['genre_label'] ?></p>
 <p>Tags: <?= join(', ', $review['tags']) ?></p>
 <p><?= nl2br($review['text']) ?></p>

 </body>
</html>

