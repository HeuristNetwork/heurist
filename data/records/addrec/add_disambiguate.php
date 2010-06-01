<?php

require_once(dirname(__FILE__).'/../../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../../common/t1000/t1000.php');
require_once(dirname(__FILE__).'/../disambig/similar.php');

if (! is_logged_in()) {
        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
        return;
}

if (! $_REQUEST['bkmk_url']) {
	header('Location: '.HEURIST_URL_BASE.'data/records/addrec/add.php');
	return;
}

mysql_connection_db_select(DATABASE);

$base_url = str_replace('&site_hierarchy', '', $_SERVER['REQUEST_URI']);
$use_site_hierarchy = array_key_exists('site_hierarchy', $_REQUEST);

?>
<html>
 <head><title>Heurist - Add bookmark (disambiguation)</title>

<link rel="stylesheet" type="text/css" href="../../../css/newshsseri.css">
<link rel="icon" href="../../../favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="../../../favicon.ico" type="image/x-icon">


<style type="text/css">
<!--
.common_url { color: lightgray; }
h1 { font-size: 18px; }
h2 { margin-bottom: 0px; margin-top: 5px; font-size: 18px; }
-->
</style>

 </head>

 <body>

<?= $page_header ?>

<span class="small">

<div id="main">

<table border="0" cellpadding="20" cellspacing="0" class="normal" width="90%"><tr><td>

<form action="add.php" method="get" onsubmit="var bid = elements['bib_id']; for (i=0; i < bid.length; ++i) { if (bid[i].checked) return true; } alert('Please select one of the options'); return false;">

<input type="hidden" name="bkmrk_bkmk_title" value="<?= htmlspecialchars($_REQUEST['bkmk_title']) ?>">
<input type="hidden" name="bkmrk_bkmk_url" value="<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>">
<input type="hidden" name="bkmrk_bkmk_description" value="<?= htmlspecialchars($_REQUEST['bkmk_description']) ?>">
<input type="hidden" name="f" value="<?= htmlspecialchars($_REQUEST['f']) ?>">
<input type="hidden" name="keyword" value="<?= htmlspecialchars($_REQUEST['keyword']) ?>">
<input type="hidden" name="bib_reftype" value="<?= htmlspecialchars(@$_REQUEST['bib_reftype']) ?>">

<table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" width="100%" class="normal">

<tr>
 <td style="width: 250px;"><h1>Possible duplicate URL</h1></td>
 <td>&nbsp;</td>
 <td style="width: 70px;"><b>Showing:</b></td>
  <?php if ($use_site_hierarchy) { ?>
 <td style="width: 150px;"><b>all URLs from this site</b></td>
 <td style="width: 150px;"><a href="<?= htmlspecialchars($base_url) ?>">records with similar URL</a></td>
  <?php } else { ?>
 <td style="width: 150px;"><b>records with similar URL</b></td>
 <td style="width: 150px;"><a href="<?= htmlspecialchars($base_url . '&site_hierarchy') ?>">all URLs from this site</a></td>
  <?php } ?></td>
</tr>

<tr><td colspan="5">&nbsp;</td></tr>

<?php
	if (! $use_site_hierarchy) {
		$bib_ids = similar_urls($_REQUEST['bkmk_url']);

		if (! $bib_ids) {	/* no similar URLs */

			$noproto_url = preg_replace('!^http://(?:www[.])?([^/]*).*!', '\1', $_REQUEST['bkmk_url']);	// URL minus the protocol + possibly www.
?>
<tr><td colspan="5"><b>No similar URLs have been bookmarked, but there are other URLs on <tt><?= htmlspecialchars($noproto_url) ?></tt>.</b></td></tr>
<tr><td colspan="5">&nbsp;</td></tr>
<tr><td colspan="5">You may look at <a href="<?= htmlspecialchars($base_url . '&site_hierarchy') ?>">all known URLs from the same site</a>,<br>
or <a href="add.php?bib_id=-1&bkmrk_bkmk_url=<?= urlencode($_REQUEST['bkmk_url']) ?>&bkmrk_bkmk_title=<?= urlencode($_REQUEST['bkmk_title']) ?>&bkmrk_bkmk_description=<?= urlencode($_REQUEST['bkmk_description']) ?>">add a bookmark</a> for <b><tt><?= htmlspecialchars($_REQUEST['bkmk_url']) ?></tt></b>.</td></tr>

<?php
		} else {
?>

<tr>
 <td>
  <b>Please choose URL to bookmark, then:&nbsp;&nbsp;</b>
  <input type="submit" value="Save bookmark">
 </td>
 <td colspan="4" style="text-align: right;">
  If choosing an existing URL, you can <label><b>check this box <input type="checkbox" name="force_new" value="1" style="margin: 0px; padding: 0px; border: 0px;"></b></label> to attach an additional bookmark<br>
 (this creates multiple bookmarks attached to one URL - use sparingly)
 </td>
</tr>

<tr><td colspan="5">&nbsp;</td></tr>

<tr>
 <td colspan="5">
<table border="0" class="normal" width="100%">

    <tr><td><h2>New URL:</h2></td></tr>
    <tr valign="bottom" style="background-color: lightgray;"><td>
       <label>
        <input type="radio" name="bib_id" value="-1">
        <b><?= htmlspecialchars($_REQUEST['bkmk_title']) ?></b>
       </label>
    </td>
    <td>&nbsp;&nbsp;</td>
    <td><nobr>
     &#91;<a target="_testwindow" href="<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>"
                                onClick="return checkURL(&quot;<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>&quot;);">visit</a>&#93;&nbsp;
<b><tt title="<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>"><?php
		if (strlen($_REQUEST['bkmk_url']) < 100)
			print htmlspecialchars($_REQUEST['bkmk_url']);
		else
			print htmlspecialchars(substr($_REQUEST['bkmk_url'], 0, 90) . '...')
      ?></tt></b>
 </nobr></td>
</tr>
   <tr><td><img src="../../../common/images/200.gif"></td><td colspan="2"><img src="../../../common/images/200.gif"</td></tr>
   <tr>
    <td colspan="3"><img src="../../../common/images/upleftarrow.gif" style="padding-left: 30px;">
     <span style="color: red;">This is the resource you asked to add to Heurist.  If selected it will be added as a new record.</span><br>
     <span style="padding-left: 64px;">If you select an existing record, that record will be opened for editing.</span>
    </td>
   </tr>

   <tr valign="bottom"><td><h2>URLs already in Heurist:</h2></td></tr>

<?php
		$bkmk_url = $_REQUEST['bkmk_url'];
		$bkmk_url_len = strlen($bkmk_url);

		$res = mysql_query('select * from records where rec_id in (' . join(',', $bib_ids) . ') and (rec_wg_id=0 or rec_visibility="viewable")');
		$all_bibs = array();
		while ($row = mysql_fetch_assoc($res))
			$all_bibs[$row['rec_id']] = $row;

		foreach ($bib_ids as $bib_id) {
			$row = $all_bibs[$bib_id];
			$common_url = find_commonality($row['rec_url'], $bkmk_url);
?>
    <tr valign="bottom"><td>
       <label>
        <input type="radio" name="bib_id" value="<?= $row['rec_id'] ?>">
        <?= htmlspecialchars($row['rec_title']) ?>
       </label>
    </td>
    <td>&nbsp;&nbsp;</td>
    <td><nobr>
       &#91;<a target="_testwindow" href="<?= htmlspecialchars($row['rec_url']) ?>"
                                                          onClick="return checkURL(&quot;<?= htmlspecialchars($row['rec_url']) ?>&quot;);">visit</a>&#93;&nbsp; <tt title="<?= htmlspecialchars($row['rec_url']) ?>"><?php
/*
			if (strlen($row['rec_url']) < 100)
				print htmlspecialchars($row['rec_url']);
			else
				print htmlspecialchars(substr($row['rec_url'], 0, 90) . '...');
*/
			$common_url_len = strlen($common_url);
			print '<span class="common_url" title="'.htmlspecialchars($row['rec_url']).'">'.htmlspecialchars($common_url).'</span>'.substr($row['rec_url'], $common_url_len, $bkmk_url_len-$common_url_len + 10);
			if (strlen($row['rec_url']) > $bkmk_url_len+10) print '...';
        ?></tt>
    </nobr></td></tr>
<?php
		}
?>
</table>
 </td>
</tr>

</table>
<?php
		}
	} else {
		$bkmk_url = $_REQUEST['bkmk_url'];
		$bkmk_url_len = strlen($bkmk_url);

		$site = preg_replace('!^http://([^/]+).*!', '$1', $bkmk_url);
?>

<tr>
 <td>
  <b>Please choose URL to bookmark, then:&nbsp;&nbsp;</b>
  <input type="submit" value="Save bookmark">
 </td>
 <td colspan="4" style="text-align: right;">
  If choosing an existing URL, you can <label><b>check this box <input type="checkbox" name="force_new" value="1" style="margin: 0px; padding: 0px; border: 0px;"></b></label> to attach an additional bookmark<br>
 (this creates multiple bookmarks attached to one URL - use sparingly)
 </td>
</tr>

<tr><td colspan="5">&nbsp;</td></tr>

<tr>
 <td colspan="5">
<table border="0" class="normal" width="100%">

   <tr><td><h2>New URL:</h2></td></tr>

   <tr valign="bottom" style="background-color: lightgray;">
   <td><input type="radio" name="bib_id" value="-1">
       <b><?= htmlspecialchars($_REQUEST['bkmk_title']) ?></b></td>
   <td>&nbsp;&nbsp;</td>
   <td><nobr>
       &#91;<a target="_testwindow" href="<?= htmlspecialchars($bkmk_url) ?>"
               onClick="return checkURL(&quot;<?= htmlspecialchars($bkmk_url) ?>&quot;);">visit</a>&#93;&nbsp;
            <tt title="<?= htmlspecialchars($bkmk_url) ?>"><b><?= htmlspecialchars($bkmk_url) ?></b></tt>
   </nobr></td>
   </tr>

   <tr valign="bottom"><td><h2>URLs already in Heurist:</h2></td></tr>
<?php
		$all_site_urls = site_urls($bkmk_url);
		foreach ($all_site_urls as $url => $id_title) {
			$common_url = find_commonality($url, $bkmk_url);
?>
    <tr valign="bottom"><td>
       <label>
        <input type="radio" name="bib_id" value="<?= $id_title[0] ?>">
        <?= htmlspecialchars($id_title[1]) ?>
       </label>
    </td>
    <td>&nbsp;&nbsp;</td>
    <td><nobr>
       &#91;<a target="_testwindow" href="<?= htmlspecialchars($url) ?>"
                                                          onClick="return checkURL(&quot;<?= htmlspecialchars($url) ?>&quot;);">visit</a>&#93;&nbsp; <tt><?php
/*
			if (strlen($url) < 100)
				print htmlspecialchars($url);
			else
				print htmlspecialchars(substr($url, 0, 90) . '...')
*/
			$common_url_len = strlen($common_url);
			print '<span class="common_url" title="'.htmlspecialchars($url).'">'.htmlspecialchars($common_url).'</span>'.substr($url, $common_url_len, $bkmk_url_len-$common_url_len + 10);
			if (strlen($url) > $bkmk_url_len+10) print '...';

        ?></tt>
    </nobr></td></tr>
<?php
		}
?>
   <tr><td><img src="../../../common/images/200.gif"></td><td colspan="2"></td></tr>
</table>
 </td>
</tr>

</table>

<?php
	}
?>
</form>

</td></tr></table>

<div style="position: absolute; display: none; background: url('<?=HEURIST_SITE_PATH?>common/images/100x100.gif');" id="popupProtector"></div>

<div style="background-color: #600000; border: 0px; margin: 0px; padding: 10px; overflow: hidden; display: none; position: absolute; text-align: center;" id="url_checker">
 <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" style="font-size: 12px;">
  <tr>
   <td style="color: white; font-weight: bold;">Previewing bookmark</td>
   <td style="width: 150px; text-align: right;"><a style="text-decoration: none; color: white;" onClick="endCheckURL(); return false;" href='<?=HEURIST_SITE_PATH?>common/messages/blank.html'>[close this window]</a></td>
  </tr>
  <tr>
   <td colspan="2"><iframe style="border: 0px; margin: 0px; padding: 0px; background-color: white;" frameBorder="0" name="uc_frame" id="uc_frame_obj" src='<?=HEURIST_SITE_PATH?>common/messages/blank.html'></iframe></td>
  </tr>
 </table>
</div>

</body>

</html>
<?php

function find_commonality($url1, $url2) {

	$url1_slash_pos = strpos($url1, '/', 7);
	if (! $url1_slash_pos) return $url1;
	$url2_slash_pos = strpos($url2, '/', 7);
	if (! $url2_slash_pos) return $url1;

	$url1_  = substr($url1, $url1_slash_pos);
	$url2_  = substr($url2, $url2_slash_pos);

	$url1_split = preg_split('!(?<=[/?&])!', $url1_);
	$url2_split = preg_split('!(?<=[/?&])!', $url2_);

	$common = substr($url1, 0, $url1_slash_pos);
	$maxi = min(count($url1_split), count($url2_split));
	for ($i=0; $i < $maxi; ++$i)
		if ($url1_split[$i] == $url2_split[$i])
			$common .= $url1_split[$i];
		else
			break;

	return $common;
}

?>
