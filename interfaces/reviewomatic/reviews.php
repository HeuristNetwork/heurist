<?php

require('cred.php');
require('functions.php');

if (!is_logged_in()) {
	header('Location: login.php');
	return;
}

$reviews_per_page = 20;

$index = @$_REQUEST['i'] ? $_REQUEST['i'] : 0;

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
if ($ass_kwd_id) {
	list($reviews, $total) = get_all_reviews($class_grp_id, $ass_kwd_id, $index, $reviews_per_page);
}

?>
<html>
 <head>
  <title>Review-o-matic<?= $assignments && @$assignments[$ass_kwd_id] ? ' - ' . $assignments[$ass_kwd_id] : '' ?></title>
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
      <?php print_assignment_select($assignments, $ass_kwd_id); ?>
     </td>
    </tr>
   </table>
  </form>

  <a style="float: right;" href=./?c=<?=$class_grp_id?>&a=<?=$ass_kwd_id?>>Return to main page</a>

  <?php if ($ass_kwd_id) { ?>
  <div id=all_reviews>
   <div class=heading2>View all reviews</div>
   <?php if (count($reviews)) { ?>
   <br>
   <div><?php print_navigation($class_grp_id, $ass_kwd_id, $index, $reviews_per_page, count($reviews), $total); ?></div>
   <br>
   <br>
   <?php print_reviews_table($class_grp_id, $ass_kwd_id, $reviews, false); ?>
   <?php } else { ?>
   No reviews added yet
   <?php } ?>
  </div>
  <?php } ?>

  <a href=./?c=<?=$class_grp_id?>&a=<?=$ass_kwd_id?>>Return to main page</a>

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
		foreach ($assignments as $ass_id => $ass_name) {
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
		print "   <td><a href=".$review['url'].">".(strlen($review['url']) > 40 ? substr($review['url'],0,40)."..." : $review['url'])."</a></td>\n";
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

function print_navigation($class_grp_id, $ass_kwd_id, $index, $reviews_per_page, $count, $total) {
	$start = $index + 1;
	$end = $index + $count;
	print "reviews " . $start . " to " . $end . " of " . $total . "<br>";
	if ($index > 0) {
		print "<a href=?c=".$class_grp_id."&a=".$ass_kwd_id."&i=" . max(0, $index - $reviews_per_page) . ">";
		print "previous " . $reviews_per_page . "</a>&nbsp;&nbsp;&nbsp;&nbsp;";
	}
	if ($end < $total) {
		print "<a href=?c=".$class_grp_id."&a=".$ass_kwd_id."&i=" . ($index + $reviews_per_page) . ">";
		print "next " . $reviews_per_page . "</a>";
	}
}

?>
