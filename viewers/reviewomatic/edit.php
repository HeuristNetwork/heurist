<?php

require('cred.php');
require('functions.php');

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

$genres = get_genres($class_grp_id);

?>

<html>
 <head>
  <title>Review-o-matic - Edit review</title>
  <link rel=stylesheet type=text/css href=chesher.css>
  <style type=text/css>
#t {
	width: 100%;
	height: 100%;
}
form {
	margin: 0px;
	padding: 0px;
}
#form_table {
	width: 100%;
	height: 100%;
}
#review_text_td {
	width: 100%;
	height: 300px;
}
#review_text {
	width: 90%;
	height: 99%;
}
#form_table td {
	vertical-align: top;
}
#bottom_row {
	padding: 0 100px 0 100px;
	height: 60px;
}
#headline_input {
	width: 50%;
}

  </style>
 </head>
 <body>
 
 <form action=save.php method=post>
 <input type=hidden name=id value=<?= $_REQUEST['id'] ?>>
 <input type=hidden name=c value=<?= $class_grp_id ?>>
 <input type=hidden name=a value=<?= $ass_kwd_id ?>>
 <table id=t cellspacing=0 cellpadding=0 border=0>

 <tr><td id=banner>
   <div class=heading>Review-o-matic - Edit review</div>
 </td></tr>

 <tr><td>
  <table id=form_table cellspacing=10 cellpadding=0 border=0>

  <tr><td colspan=2>
  <div id=logged_in>
   logged in as:
   <span id=username><?= get_user_name() ?></span>
   [<a href=login.php?logout=1>log out</a>]
  </div>
   <div class=heading2><?= $classes[$class_grp_id] ?> - <?= $assignments[$ass_kwd_id] ?></div>
  </td></tr>

   <tr>
    <td><nobr>Website title</nobr></td>
    <td><?php print_title_input($review['title']); ?></td>
   </tr>

   <tr>
    <td><nobr>Website URL</nobr></td>
    <td><a href=<?= $review['url'] ?>><?= $review['url'] ?></a></td>
   </tr>

   <tr>
    <td>Genre</td>
    <td><?php print_genre_select($genres, $review['genre_id']); ?></td>
   </tr>

   <tr>
    <td>Rating</td>
    <td><?php print_rating_select($review['rating']); ?></td>
   </tr>

   <tr>
    <td>Tags</td>
    <td><?php print_tag_inputs($review['tags'], $ass_kwd_id); ?></td>
   </tr>

   <tr>
    <td>Headline</td>
    <td><?php print_headline_input($review['headline']); ?></td>
   </tr>

   <tr>
    <td>Review</td>
    <td id=review_text_td><textarea id=review_text name=text><?= htmlspecialchars($review['text']) ?></textarea></td>
   </tr>
   <tr><td></td><td>Note: you may use HTML markup within your review</td></tr>

  </table>
 </td></tr>

 <tr><td id=bottom_row>
  <br>
  <p>By clicking save I certify that:</p>
  
  <p>(1) I have read and understood the
  <a href=http://www.usyd.edu.au/senate/policies/Plagiarism.pdf>University of Sydney Student
  Plagiarism: Coursework Policy and Procedure</a>;</p>

  <p>(2) I understand that failure to comply with the Student Plagiarism:
  Coursework Policy and Procedure can lead to the University commencing
  proceedings against me for potential student misconduct under Chapter
  8 of the University of Sydney By-Law 1999 (as amended);</p>
  
  <p>(3) this Work is substantially my own, and to the extent that any
  part of this Work is not my own I have indicated that it is not my
  own by Acknowledging the Source of that part or those parts of the Work.</p>
  <br>
  <div style="text-align: center;"><input type=submit value=save></div>
 </td></tr>

 </table>
 </form>

 </body>
</html>

<?php

function print_title_input($title) {
	print "<input name=title value=\"".htmlspecialchars($title)."\">\n";
}

function print_genre_select($genres, $selected_genre) {
	print "<select id=genre_select name=genre>\n";
	if (!$selected_genre)
		print " <option selected></option>\n";
	foreach ($genres as $genre_id => $genre_name) {
		print " <option value=".$genre_id. ($selected_genre == $genre_id ? " selected" : "") .">".$genre_name."</option>\n";
	}
	print "</select>\n";
}

function print_rating_select($rating) {
	print "<select id=rating_select name=rating>\n";
	if (!$rating)
		print " <option selected></option>\n";
	for ($i = 10; $i <= 100; $i+= 10) {
		print " <option value=".$i. ($rating == $i ? " selected" : "") .">".$i."%</option>\n";
	}
	print "</select>\n";
}

function print_headline_input($headline) {
	print "<input id=headline_input name=headline value=\"".htmlspecialchars($headline)."\">\n";
}

function print_tag_inputs($tags, $ass_kwd_id) {
	for ($i = 0; $i < get_assignment_tag_count($ass_kwd_id); ++$i) {
		print "<input name=tag".$i." value=\"".@$tags[$i]."\">&nbsp;&nbsp;";
	}
}

?>
