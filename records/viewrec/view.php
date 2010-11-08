<?php

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once('replaced.php');

if (!is_logged_in()) {
        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
        return;
}

mysql_connection_db_select(DATABASE);

if (@$_REQUEST['bkmk_id']) {
	$bkm_ID = $_REQUEST['bkmk_id'];
	$res = mysql_query('select * from usrBookmarks where bkm_ID = ' . $bkm_ID);
	$bkmk = mysql_fetch_assoc($res);
	$rec_id = $bkmk['pers_rec_id'];
} else {
	$rec_id = @$_REQUEST['bib_id'];

	// check if this bib has been replaced
	$replacement = get_replacement_bib_id($rec_id);
	if ($replacement) $rec_id = $replacement;
	/*
	if (! $rec_id) {
		print '<html><body><p>No such resource</p>';
		print '<p><a href="..">Return to Heurist</a></p></body></html>';
		return;
	}
	*/

	$res = mysql_query('select * from usrBookmarks where pers_rec_id = ' . $rec_id . ' and bkm_UGrpID = ' . get_user_id());
	$bkmk = mysql_fetch_assoc($res);
	$bkm_ID = $bkmk['bkm_ID'];
}

require_once(dirname(__FILE__).'/../permissions/permissions.php');
if (! have_bib_permissions($rec_id)) {
	header('Location: ' . BASE_PATH . 'common/messages/denied.html?'.$rec_id);
	return;
}


$noclutter = array_key_exists('noclutter', $_REQUEST)? '&amp;noclutter' : '';

$res = mysql_query('select rec_title from records where rec_id = ' . $rec_id);
$row = mysql_fetch_assoc($res);
$rec_title = $row['rec_title'];

?>

<html>

<head>
  <title>HEURIST - View record</title>

  <link rel="icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">

  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/heurist.css">
</head>

<body <?php if (@$_REQUEST['popup']) { ?>style="width: 480px; height: 600px; background-color: transparent;" <?php } ?>>

<div style="background-color: black; color: white; font-weight: bold; padding: 7px 10px;">Record details</div>

<div style="padding: 10px;">
 <p style="font-weight: bold;"><?= htmlspecialchars($rec_title) ?></p>
 <iframe name="viewer" frameborder="0" style="width: 100%;height: 100%;" src="<?=HEURIST_SITE_PATH?>records/viewrec/info.php?<?= ($bkm_ID ? ('bkmk_id='.$bkm_ID) : ('bib_id='.$rec_id)) ?><?= $noclutter ?>"></iframe>
</div>

</body>
</html>

