<?php

require('cred.php');
require('functions.php');

if (!is_logged_in()) {
	header('Location: login.php');
	return;
}

// ----- load classes, assignments, reviews
$classes = get_classes(get_user_id());
if (count($classes) == 0) {
	print "you are not a member of any classes";
	return;
}
$class_grp_id = get_class_grp_id($classes);
if ($class_grp_id) {
	$assignments = get_assignments($class_grp_id);
	$ass_kwd_id = get_ass_kwd_id($assignments, $class_grp_id);
}
if (@$ass_kwd_id) {
	$reviews = get_reviews(get_user_id(), $class_grp_id, $ass_kwd_id);
}

?>
<html>
 <head>
  <title>Review-o-matic<?= @$assignments && @$assignments[$ass_kwd_id] ? ' - ' . $assignments[$ass_kwd_id] : '' ?></title>
  <link rel=stylesheet type=text/css href=chesher.css>
  <style type=text/css>
table {
	margin: 5px 0px;
	padding-bottom: 5px;
}
#details, #your_reviews, #all_reviews {
	padding: 10px 0px;
}
#your_reviews table, #all_reviews table {
	border: 1px solid black;
}
#your_reviews table td, #your_reviews table th, #all_reviews table td, #all_reviews table th {
	padding-right: 20px;
}
  </style>
 </head>
 <body>
 
  <div id=banner>
   <div class=heading>Review-o-matic</div>
  </div>
   
  <div id=logged_in>
   logged in as:
   <span id=username><?= get_user_name() ?></span>
   [<a href=login.php?logout=1>log out</a>]
  </div>

  <form>
   <table cellpadding=0 cellspacing=0>
    <tr>
     <td style="padding-right: 20px;">
      Class:
      <?php print_class_select($classes, $class_grp_id); ?>
     </td>
     <td>
      Assignment:
      <?php print_assignment_select(@$assignments, @$ass_kwd_id); ?>
     </td>
    </tr>
   </table>
  </form>

  <?php if ($ass_kwd_id) { ?>
  <div id=details>
   <div class=heading2>Assignment details</div>
   <?= get_assignment_details($ass_kwd_id) ?>
  </div>

  <div id=your_reviews>
   <div class=heading2>Your reviews</div>
   <?php if (count($reviews)) print_reviews_table($class_grp_id, $ass_kwd_id, $reviews); ?>
   <br>
   <?php if (count($reviews) < get_assignment_review_count($ass_kwd_id)) { ?>
   <a href=add.php?c=<?=$class_grp_id?>&a=<?=$ass_kwd_id?>>Add a review</a>
   &nbsp;&nbsp;
   <?php } ?>
   <?php if (count($reviews)) { ?>
   <a href=print.php?c=<?=$class_grp_id?>&a=<?=$ass_kwd_id?>>Print reviews for submission</a>
   <?php } ?>
  </div>

  <br>

  <a href=reviews.php?c=<?=$class_grp_id?>&a=<?=$ass_kwd_id?>>Click here to read other reviews</a>

  <br>
  <br>
  <br>

  <?php } ?>

 </body>
</html>

<?php


function print_class_select($classes, $selected_class) {
	if (count($classes) != 1) {
		print "<select id=class_select name=c onchange=\"form.submit();\">\n";
		if (!$selected_class)
			print " <option selected></option>\n";
		foreach ($classes as $class_id => $class_name) {
			print " <option value=".$class_id. ($selected_class == $class_id ? " selected" : "") .">".$class_name."</option>\n";
		}
		print "</select>\n";
	} else {
		print $classes[$selected_class] . "\n";
	}
}

function print_assignment_select($assignments, $selected_ass) {
	if (count($assignments) != 1) {
		print "<select id=ass_select name=a onchange=form.submit();>\n";
		if (!$selected_ass)
			print " <option selected></option>\n";
		foreach (@$assignments as $ass_id => $ass_name) {
			print " <option value=".$ass_id. ($selected_ass == $ass_id ? " selected" : "") .">".str_replace('Assignment: ', '', $ass_name)."</option>\n";
		}
		print "</select>\n";
	} else {
		print $assignments[$selected_ass] . "\n";
	}
}

function print_reviews_table($class_grp_id, $ass_kwd_id, $reviews, $own_reviews=true) {
	print "<table>\n";
	print " <thead>\n";
	print "  <tr><th>Site</th><th>URL</th><th>Genre</th>" . ($own_reviews ? "<th></th><th></th>" : "<th>Review by</th>") . "</tr>\n";
	print " </thead>\n";
	print " <tbody>\n";
	foreach ($reviews as $review) {
		print "  <tr>\n";
		print "   <td>".$review['title']."</td>\n";
		print "   <td><a href=".$review['url'].">".$review['url']."</a></td>\n";
		print "   <td>".$review['genre_label']."</td>\n";
		if (! $own_reviews)
			print "   <td>".$review['author']."</td>\n";
		print "   <td><a target=_blank href=view.php?c=".$class_grp_id."&a=".$ass_kwd_id."&id=".$review['bkmk_id'].">view</a></td>\n";
		if ($own_reviews) {
			print "   <td><a href=edit.php?c=".$class_grp_id."&a=".$ass_kwd_id."&id=".$review['bkmk_id'].">edit</a></td>\n";
			print "   <td><a href=delete.php?c=".$class_grp_id."&a=".$ass_kwd_id."&id=".$review['bkmk_id'].">delete</a></td>\n";
		}
		print "  </tr>\n";
	}
	print " </tbody>\n";
	print "</table>\n";
}

?>
