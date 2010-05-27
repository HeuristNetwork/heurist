<?php
require_once(dirname(__FILE__).'/../connect/cred.php');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
 <title>Heurist Help: Legend and Reference Types</title>
 <link rel="stylesheet" type="text/css" href= "<?=HEURIST_SITE_PATH?>css/heurist.css">
</head>

<body width=300 height=600>

<style type=text/css>
div * { vertical-align: middle; }
div { line-height: 18px; }
</style>

<p><b>Heurist legend</b></p>

<h3>Search list icons </h3>
<div><img src="<?=HEURIST_SITE_PATH?>common/images/rss_feed_add.gif" height=12 width=12>&nbsp;Add live bookmark</div>
     <!-- <div><img src="../../pix/home_favourite.gif">&nbsp;Favourite search</div> -->
     <div><img src="<?=HEURIST_SITE_PATH?>common/images/edit_pencil_16x16.gif">&nbsp;Edit the reference</div>
     <div><img src="<?=HEURIST_SITE_PATH?>common/images/follow_links_16x16.gif">&nbsp;Detail/tools</div>
     <!-- <div><img src="../external_link_16x16.gif">&nbsp;Open in new window</div> -->
     <div><img src="<?=HEURIST_SITE_PATH?>common/images/key.gif">&nbsp;Password reminder</div>

     <div><b>&nbsp;</b></div>
     <h3><b>Reference types</b>
     </h3>
<?php
require_once(dirname(__FILE__).'/../connect/db.php');
	mysql_connection_db_select(DATABASE);
	$res = mysql_query('select rt_id, rt_name from active_rec_types left join rec_types on rt_id=art_id order by rt_id');
	while ($row = mysql_fetch_row($res)) {
?>
     <div><img src="<?=HEURIST_SITE_PATH?>common/images/reftype-icons/<?= $row[0] ?>.gif">&nbsp;<?= htmlspecialchars($row[1]) ?></div>
<?php
	}
?>
  </p>

</body>
</html>
