<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/


require_once(dirname(__FILE__).'/../connect/applyCredentials.php');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>Legend & Reference Types</title>
<link rel="stylesheet" type="text/css" href= "<?=HEURIST_SITE_PATH?>common/css/global.css">
<style type=text/css>
	div { line-height: 22px; border-bottom:1px solid #DDD;}
	div.column{ vertical-align: top;width:250px; position: absolute;top:0; border: none; }
	.right {left:280px;}
	.left {left:10px}
	img {vertical-align: text-bottom;}
</style>
</head>

<body class="popup" width=580 height=400>

<div class="column left">
	<h3>Search list icons </h3>
	<div><img src="<?=HEURIST_SITE_PATH?>common/images/logo_rss_feed.png" height=12 width=12>&nbsp;Add live bookmark</div>
    <!-- <div><img src="../../pix/home_favourite.gif">&nbsp;Favourite search</div> -->
    <div><img src="<?=HEURIST_SITE_PATH?>common/images/edit-pencil.png">&nbsp;Edit the reference</div>
    <div><img src="<?=HEURIST_SITE_PATH?>common/images/follow_links_16x16.gif">&nbsp;Detail/tools</div>
    <!-- <div><img src="../external_link_16x16.gif">&nbsp;Open in new window</div> -->
    <div><img src="<?=HEURIST_SITE_PATH?>common/images/key.gif">&nbsp;Password reminder</div>
</div>
<div class="column right">
     <h3>Reference types</h3>
<?php
require_once("dbMySqlWrappers.php");
	mysql_connection_db_select(DATABASE);
	$res = mysql_query('select rty_ID, rty_Name from  defRecTypes order by rty_ID');
	while ($row = mysql_fetch_row($res)) {
?>
     <div>
     	<table><tr>
     	<td width="24px" align="right"><font color="#CCC"><?= $row[0] ?>&nbsp;</font></td>
     	<td width="24px" align="center">
     		<img class="rft" style="background-image:url(<?=HEURIST_ICON_SITE_PATH.$row[0].".png)"?>" src="<?=HEURIST_SITE_PATH.'common/images/16x16.gif'?>">
     	</td>
     	<td><?= htmlspecialchars($row[1]) ?></td>
     	</tr></table>
</div>
<?php
	}
?>
  </p>
</div>
</body>
</html>
