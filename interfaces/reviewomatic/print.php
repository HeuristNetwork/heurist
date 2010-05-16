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

$reviews = get_reviews(get_user_id(), $class_grp_id, $ass_kwd_id, true);

?>

<html>
 <head>
  <title>Review-o-matic - Print reviews</title>
  <link rel=stylesheet type=text/css href=chesher.css>
 </head>
 <body>

 <div id=logged_in>
  <div id=username><?= get_user_name() ?></div>
  <div><?= date('jS F, Y') ?></div>
 </div>

 <div class=heading2><?= $classes[$class_grp_id] ?> - <?= $assignments[$ass_kwd_id] ?></div>
 <br>
 <br>

<?php foreach ($reviews as $review) { ?>

 <hr>
 <p class=heading2><?= $review['title'] ?></p>
 <p><?= $review['url'] ?></p>
 <p>Genre: <?= $review['genre_label'] ?></p>
 <p><?= nl2br($review['text']) ?></p>

<?php } ?>

 </body>
</html>

