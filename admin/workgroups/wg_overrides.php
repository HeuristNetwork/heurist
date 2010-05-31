<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

if (! is_logged_in()) {
	header("Location: ".HEURIST_URL_BASE."common/connect/login.php");
	return;
}
if (! is_admin()) {
	print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE."search/heurist-search.html>Return to Heurist</a></p></body></html>";
	return;
}

?>
<html>
 <head>
  <title>Workgroup overrides</title>
  <link rel="icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>css/newshsseri.css">
  <style>
   div#page { padding: 10px; }
   div#page .headline { margin-bottom: 20px; font-size: 150%; }
   div#page a { margin-left: 20px; }
  </style>
 </head>
 <body>
<?php print(file_get_contents("menu.html")); ?>
  <div id=page>
   <div class=headline>Record types with overrides - Instance: <?= HEURIST_INSTANCE ? HEURIST_INSTANCE : "Heurist Primary" ?></div>

<div class=headline>Instance overrides</div>

<?php

mysql_connection_db_select(DATABASE);

$res = mysql_query("select distinct rt_id, rt_name from rec_detail_requirements_overrides left join rec_types on rt_id=rdr_rec_type where rdr_wg_id = 0;");

if (mysql_num_rows($res) == 0) {
	print("<p><a>None</a></p>");
}
while ($row = mysql_fetch_assoc($res)) {
	print("<p><a href='../rectypes/bib_detail_editor.php?grp_id=0&rt_id=".$row["rt_id"]."'>".$row["rt_name"]."</a></p>");
}

?>

<div class=headline>Workgroup overrides</div>

<?php

$res = mysql_query("select distinct grp_id, grp_name, rt_id, rt_name from rec_detail_requirements_overrides left join ".USERS_DATABASE.".Groups on grp_id=rdr_wg_id left join rec_types on rt_id=rdr_rec_type where rdr_wg_id > 0;");

if (mysql_num_rows($res) == 0) {
	print("<p><a>None</a></p>");
}
$grp = 0;
while ($row = mysql_fetch_assoc($res)) {
	if ($row["grp_id"] != $grp) {
		print("<h2>".$row["grp_name"]."</h2>");
		$grp = $row["grp_id"];
	}
	print("<p><a href='../rectypes/bib_detail_editor.php?grp_id=".$row["grp_id"]."&rt_id=".$row["rt_id"]."'>".$row["rt_name"]."</a></p>");
}

?>
  </div>
 </body>
</html>
